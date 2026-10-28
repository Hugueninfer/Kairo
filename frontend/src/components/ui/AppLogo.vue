<script setup lang="ts">
import logo from '@/assets/logo.png'
import mark from '@/assets/logo-mark.png'

/** Logo do Kairo: a marca completa ou só o "O" (para espaços pequenos). */
withDefaults(defineProps<{ variant?: 'full' | 'mark'; height?: number }>(), { variant: 'full', height: 22 })
</script>

<template>
  <img :src="variant === 'mark' ? mark : logo" alt="Kairo" :style="{ height: `${height}px` }" class="w-auto select-none" draggable="false" />
</template>
