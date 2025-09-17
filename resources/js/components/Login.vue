<script setup lang="ts">
import { ref } from 'vue'

const email = ref('')
const password = ref('')
const processing = ref(false)
const error = ref('')

async function login() {
  error.value = ''
  processing.value = true
  try {
    await fetch('/sanctum/csrf-cookie', { credentials: 'include' })
    const resp = await fetch('/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'include',
      body: JSON.stringify({ email: email.value, password: password.value, remember: true }),
    })
    if (!resp.ok) throw new Error('Login failed')
    location.reload()
  } catch (e) {
    error.value = 'Invalid credentials.'
  } finally {
    processing.value = false
  }
}
</script>

<template>
  <div class="space-y-3">
    <div>
      <label class="block text-sm mb-1">Email</label>
      <input v-model="email" type="email" class="w-full border rounded px-3 py-2" />
    </div>
    <div>
      <label class="block text-sm mb-1">Password</label>
      <input v-model="password" type="password" class="w-full border rounded px-3 py-2" />
    </div>
    <div class="text-sm text-red-600" v-if="error">{{ error }}</div>
    <button @click="login" :disabled="processing" class="px-4 py-2 rounded bg-black text-white disabled:opacity-50">{{ processing ? 'Signing in…' : 'Sign in' }}</button>
  </div>
</template>

<style scoped>
</style>


