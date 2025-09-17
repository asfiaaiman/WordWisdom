<script setup lang="ts">
import { ref } from 'vue'

const name = ref('')
const email = ref('')
const password = ref('')
const password_confirmation = ref('')
const processing = ref(false)
const error = ref('')

async function register() {
  error.value = ''
  processing.value = true
  try {
    await fetch('/sanctum/csrf-cookie', { credentials: 'include' })
    const resp = await fetch('/register', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'include',
      body: JSON.stringify({ name: name.value, email: email.value, password: password.value, password_confirmation: password_confirmation.value }),
    })
    if (!resp.ok) throw new Error('Register failed')
    location.reload()
  } catch (e) {
    error.value = 'Registration failed.'
  } finally {
    processing.value = false
  }
}
</script>

<template>
  <div class="space-y-3">
    <div>
      <label class="block text-sm mb-1">Name</label>
      <input v-model="name" type="text" class="w-full border rounded px-3 py-2" />
    </div>
    <div>
      <label class="block text-sm mb-1">Email</label>
      <input v-model="email" type="email" class="w-full border rounded px-3 py-2" />
    </div>
    <div>
      <label class="block text-sm mb-1">Password</label>
      <input v-model="password" type="password" class="w-full border rounded px-3 py-2" />
    </div>
    <div>
      <label class="block text-sm mb-1">Confirm Password</label>
      <input v-model="password_confirmation" type="password" class="w-full border rounded px-3 py-2" />
    </div>
    <div class="text-sm text-red-600" v-if="error">{{ error }}</div>
    <button @click="register" :disabled="processing" class="px-4 py-2 rounded bg-black text-white disabled:opacity-50">{{ processing ? 'Creating…' : 'Create account' }}</button>
  </div>
</template>

<style scoped>
</style>


