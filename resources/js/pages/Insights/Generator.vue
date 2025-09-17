<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import type { AppPageProps } from '@/types'
declare const route: any

type PageProps = AppPageProps<{
  curatedWords: string[]
  wordDefinitions: Record<string, string>
  curatedTopics: string[]
  flash?: { generated?: { word: string; topic: string; tone?: string | null; content: string; entities?: { text: string; label: string; start: number; end: number }[]; summary?: string; language?: { lang: string; confidence: number }; translated?: string; target_lang?: string; keywords?: { phrase: string; score: number }[]; wisdomChain?: string[] } }
}>

const page = usePage<PageProps>()
const curatedWords = computed(() => page.props.curatedWords || [])
const curatedTopics = computed(() => page.props.curatedTopics || [])
const wordDefinitions = computed(() => page.props.wordDefinitions || {})
const liveDefinition = ref('')

const form = useForm({
  word: '',
  topic: '',
  article: '',
  tone: '',
  target_lang: 'en',
  enable_ner: true,
  enable_summary: true,
  enable_keywords: true,
  enable_translation: true,
  async: false,
  save: true as boolean | null,
})

const generated = ref<{ word: string; topic: string; tone?: string | null; content: string; entities?: { text: string; label: string; start: number; end: number }[]; summary?: string; language?: { lang: string; confidence: number }; translated?: string; target_lang?: string; keywords?: { phrase: string; score: number }[]; wisdomChain?: string[] } | null>(null)
const canvasRef = ref<HTMLCanvasElement | null>(null)

onMounted(() => {
  const flashAny = page.props as any
  const fromFlash = flashAny?.flash?.generated
  if (fromFlash) generated.value = fromFlash
})

function pickWord(word: string) {
  form.word = word
}
function pickTopic(topic: string) {
  form.topic = topic
}
async function fetchDefinition() {
  liveDefinition.value = ''
  const w = form.word?.trim()
  if (!w || wordDefinitions.value[w]) return
  try {
    const resp = await fetch(route('api.definitions.show') + '?word=' + encodeURIComponent(w))
    if (!resp.ok) return
    const data = await resp.json()
    liveDefinition.value = data.definition || ''
  } catch {}
}

function submit() {
  form.post(route('insights.store'))
}

async function saveInsight() {
  if (!generated.value) return
  await fetch('/sanctum/csrf-cookie', { credentials: 'include' })
  const resp = await fetch(route('api.insights.store'), {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'include',
    body: JSON.stringify({
      word: generated.value.word,
      topic: generated.value.topic,
      tone: generated.value.tone || null,
      content: generated.value.content,
    }),
  })
  if (resp.ok) alert('Saved!')
  else alert('Failed to save (are you logged in?)')
}

function tweetUrl(text: string) {
  const url = new URL('https://twitter.com/intent/tweet')
  url.searchParams.set('text', text)
  return url.toString()
}

function fbUrl(text: string) {
  const url = new URL('https://www.facebook.com/sharer/sharer.php')
  url.searchParams.set('u', 'https://wordwisdom.test')
  url.searchParams.set('quote', text)
  return url.toString()
}

function copyGeneratedText() {
  if (!generated.value) return
  navigator.clipboard?.writeText(generated.value.content)
}

const highlightSegments = computed(() => {
  const g = generated.value
  if (!g) return [] as { text: string; label?: string }[]
  const text = g.content
  const ents = (g.entities || []).slice().sort((a, b) => a.start - b.start || (b.end - b.start) - (a.end - a.start))
  const filtered: typeof ents = []
  let lastEnd = -1
  for (const e of ents) {
    if (e.start < lastEnd) continue
    filtered.push(e)
    lastEnd = e.end
  }
  const out: { text: string; label?: string }[] = []
  let cursor = 0
  for (const e of filtered) {
    if (e.start > cursor) out.push({ text: text.slice(cursor, e.start) })
    out.push({ text: text.slice(e.start, e.end), label: e.label })
    cursor = e.end
  }
  if (cursor < text.length) out.push({ text: text.slice(cursor) })
  return out
})

function downloadImage() {
  if (!generated.value) return
  const fd = new FormData()
  fd.set('text', generated.value.content)
  fd.set('word', generated.value.word)
  fd.set('topic', generated.value.topic)
  fetch(route('image.generate'), { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || '' } })
    .then(async (res) => {
      if (!res.ok) throw new Error('Failed to generate image')
      const blob = await res.blob()
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = 'wordwisdom.png'
      a.click()
      setTimeout(() => URL.revokeObjectURL(url), 1000)
    })
    .catch(() => alert('Image generation failed'))
}
</script>

<template>
  <Head title="Generate Insight" />
  <div class="max-w-5xl mx-auto p-6 space-y-8">
    <h1 class="text-3xl font-semibold">WordWisdom Generator</h1>

    <div class="grid md:grid-cols-3 gap-6">
      <div class="md:col-span-2 space-y-6">
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1">Word</label>
            <input v-model="form.word" @blur="fetchDefinition" type="text" class="w-full border rounded px-3 py-2" placeholder="e.g. hegemony" />
            <div class="flex flex-wrap gap-2 mt-2">
              <button v-for="w in curatedWords" :key="w" @click="pickWord(w)" type="button" class="px-2 py-1 text-sm rounded bg-gray-100 hover:bg-gray-200">
                {{ w }}
              </button>
            </div>
            <p v-if="form.word && wordDefinitions[form.word]" class="mt-2 text-sm text-gray-600">{{ wordDefinitions[form.word] }}</p>
            <p v-else-if="liveDefinition" class="mt-2 text-sm text-gray-600">{{ liveDefinition }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Topic</label>
            <input v-model="form.topic" type="text" class="w-full border rounded px-3 py-2" placeholder="e.g. climate change" />
            <div class="flex flex-wrap gap-2 mt-2">
              <button v-for="t in curatedTopics" :key="t" @click="pickTopic(t)" type="button" class="px-2 py-1 text-sm rounded bg-gray-100 hover:bg-gray-200">
                {{ t }}
              </button>
            </div>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-1">Optional Article / Long Text</label>
          <textarea v-model="form.article" rows="6" class="w-full border rounded px-3 py-2" placeholder="Paste an article or long paragraph to summarize and base the insight on"></textarea>
          <p class="mt-1 text-xs text-gray-500">If provided, article text will be summarized to 1–2 sentences and influence the generated insight.</p>
        </div>

        <div class="grid sm:grid-cols-2 gap-4 items-end">
          <div>
            <label class="block text-sm font-medium mb-1">Tone (optional)</label>
            <input v-model="form.tone" type="text" class="w-full border rounded px-3 py-2" placeholder="e.g. hopeful, critical, ironic" />
          </div>
          <div class="flex items-center gap-3">
            <label class="inline-flex items-center gap-2">
              <span class="text-sm">Target language</span>
              <select v-model="form.target_lang" class="border rounded px-2 py-1 text-sm">
                <option value="en">English (en)</option>
                <option value="es">Spanish (es)</option>
                <option value="fr">French (fr)</option>
                <option value="de">German (de)</option>
                <option value="it">Italian (it)</option>
                <option value="pt">Portuguese (pt)</option>
                <option value="ru">Russian (ru)</option>
                <option value="ar">Arabic (ar)</option>
                <option value="zh">Chinese (zh)</option>
                <option value="ja">Japanese (ja)</option>
              </select>
            </label>
            <label class="inline-flex items-center gap-2">
              <input type="checkbox" v-model="form.save" class="rounded" />
              <span>Save to my profile</span>
            </label>
            <button @click="submit" :disabled="form.processing" class="ml-auto px-4 py-2 rounded bg-black text-white hover:bg-gray-800 disabled:opacity-50">
              {{ form.processing ? 'Generating…' : 'Generate Insight' }}
            </button>
          </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" v-model="form.enable_ner" class="rounded" />
            <span>Enable NER</span>
          </label>
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" v-model="form.enable_summary" class="rounded" />
            <span>Summarize</span>
          </label>
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" v-model="form.enable_keywords" class="rounded" />
            <span>Extract Keywords</span>
          </label>
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" v-model="form.enable_translation" class="rounded" />
            <span>Translate to target</span>
          </label>
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" v-model="form.async" class="rounded" />
            <span>Process asynchronously</span>
          </label>
        </div>
      </div>

      <aside class="space-y-3">
        <div class="p-4 rounded border bg-white">
          <h3 class="font-medium mb-2">How it works</h3>
          <p class="text-sm text-gray-700">Pick a word and a topic, we craft a concise, profound insight you can share.</p>
        </div>
        <div class="p-4 rounded border bg-white">
          <h3 class="font-medium mb-2">Go viral, thoughtfully</h3>
          <p class="text-sm text-gray-700">One or two elegant sentences designed for social feeds.</p>
        </div>
      </aside>
    </div>

    <div v-if="generated" class="space-y-4">
      <div class="p-6 rounded-lg border bg-white">
        <div class="text-xs uppercase tracking-wide text-gray-500 mb-2">Generated Insight</div>
        <div class="text-xl leading-relaxed">
          <template v-for="(seg, i) in highlightSegments" :key="i">
            <span v-if="!seg.label">{{ seg.text }}</span>
            <span v-else class="bg-yellow-100 rounded px-0.5" :title="seg.label">{{ seg.text }}</span>
          </template>
        </div>
        <div class="mt-3 text-sm text-gray-500">Word: <span class="font-medium">{{ generated!.word }}</span> • Topic: <span class="font-medium">{{ generated!.topic }}</span></div>
      </div>

        <div v-if="generated?.language" class="p-4 rounded border bg-white">
          <div class="text-xs uppercase tracking-wide text-gray-500 mb-2">Detected Language</div>
          <div class="text-sm">{{ generated!.language!.lang }} <span class="text-gray-500">({{ Math.round((generated!.language!.confidence || 0) * 100) }}%)</span></div>
        </div>

        <div v-if="generated?.translated && generated.translated !== generated.content" class="p-4 rounded border bg-white">
          <div class="text-xs uppercase tracking-wide text-gray-500 mb-2">Translation ({{ generated?.target_lang || 'en' }})</div>
          <div class="text-base leading-relaxed">{{ generated!.translated }}</div>
        </div>

        <div v-if="generated?.summary" class="p-4 rounded border bg-white">
          <div class="text-xs uppercase tracking-wide text-gray-500 mb-2">Summary</div>
          <div class="text-base leading-relaxed">{{ generated!.summary }}</div>
        </div>

        <div v-if="generated?.keywords?.length" class="p-4 rounded border bg-white">
          <div class="text-xs uppercase tracking-wide text-gray-500 mb-2">Keywords</div>
          <div class="flex flex-wrap gap-2">
            <span v-for="(k, i) in generated!.keywords!" :key="i" class="px-2 py-1 rounded-full text-xs bg-gray-100">
              {{ k.phrase }}
            </span>
          </div>
        </div>

        <div v-if="generated?.wisdomChain?.length" class="p-4 rounded border bg-white">
          <div class="text-xs uppercase tracking-wide text-gray-500 mb-2">Wisdom Chain</div>
          <div class="flex flex-wrap gap-2">
            <button v-for="(w, i) in generated!.wisdomChain!" :key="i" type="button" @click="pickWord(w)" class="px-2 py-1 rounded bg-indigo-50 text-indigo-700 hover:bg-indigo-100">
              {{ w }}
            </button>
          </div>
        </div>

      <div class="flex gap-3">
        <a :href="tweetUrl(generated!.content)" target="_blank" class="px-3 py-2 rounded bg-blue-500 text-white hover:bg-blue-600">Share on X</a>
        <a :href="fbUrl(generated!.content)" target="_blank" class="px-3 py-2 rounded bg-blue-700 text-white hover:bg-blue-800">Share on Facebook</a>
        <button @click="copyGeneratedText" class="px-3 py-2 rounded border">Copy text</button>
        <button @click="downloadImage" class="px-3 py-2 rounded border">Download image</button>
        <button @click="saveInsight" class="px-3 py-2 rounded border">Save to Profile</button>
      </div>
      <div v-if="generated?.entities?.length" class="p-4 rounded border bg-white">
        <div class="text-sm font-medium mb-2">Entities</div>
        <ul class="text-sm text-gray-700 grid sm:grid-cols-2 gap-x-6">
          <li v-for="(e, idx) in generated!.entities!" :key="idx" class="flex items-center gap-2">
            <span class="px-1 rounded bg-yellow-100">{{ e.text }}</span>
            <span class="text-xs uppercase tracking-wide text-gray-500">{{ e.label }}</span>
          </li>
        </ul>
      </div>
      <canvas ref="canvasRef" class="hidden"></canvas>
    </div>
  </div>
</template>

<style scoped>
</style>


