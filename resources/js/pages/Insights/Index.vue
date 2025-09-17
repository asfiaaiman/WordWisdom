<script setup lang="ts">
import { Head, usePage, router } from '@inertiajs/vue3'

type Insight = { id: number; word: string; topic: string; tone?: string | null; content: string; created_at: string }
type Paginated<T> = { data: T[]; links: { url: string | null; label: string; active: boolean }[] }

const page = usePage<{ insights: Paginated<Insight> }>()
const insights = page.props.insights

function tweetUrl(text: string) {
  const url = new URL('https://twitter.com/intent/tweet')
  url.searchParams.set('text', text)
  return url.toString()
}
</script>

<template>
  <Head title="My Insights" />
  <div class="max-w-5xl mx-auto p-6 space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-3xl font-semibold">My Insights</h1>
      <a :href="route('insights.create')" class="px-3 py-2 rounded bg-black text-white">Create new</a>
    </div>

    <div v-if="insights.data.length === 0" class="p-8 text-center text-gray-600 border rounded">No insights yet.</div>

    <div class="grid md:grid-cols-2 gap-4">
      <div v-for="item in insights.data" :key="item.id" class="p-5 rounded border bg-white space-y-2">
        <div class="text-sm text-gray-500">{{ new Date(item.created_at).toLocaleString() }}</div>
        <div class="text-lg leading-relaxed">{{ item.content }}</div>
        <div class="text-sm text-gray-500">Word: <span class="font-medium">{{ item.word }}</span> • Topic: <span class="font-medium">{{ item.topic }}</span></div>
        <div class="pt-2 flex gap-2">
          <a :href="tweetUrl(item.content)" target="_blank" class="px-2 py-1 rounded bg-blue-500 text-white text-sm">Share</a>
          <button @click="() => navigator.clipboard.writeText(item.content)" class="px-2 py-1 rounded border text-sm">Copy</button>
        </div>
      </div>
    </div>

    <div class="flex gap-1 flex-wrap" v-if="insights.links?.length">
      <a v-for="l in insights.links" :key="l.label" :href="l.url || '#'" :class="['px-3 py-1 rounded border text-sm', l.active ? 'bg-black text-white' : 'bg-white']" v-html="l.label" />
    </div>
  </div>
</template>

<style scoped>
</style>


