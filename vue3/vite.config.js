import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    }
  },
  build: {
    outDir: 'amd/build',
    emptyOutDir: true,
    lib: {
      entry: 'main.js',
      name: 'local_adele',
      fileName: () => 'main.js'
    },
    rollupOptions: {
      external: [
        'core/ajax',      // Moodle AMD
        'core/str',       // wenn du auch core/str nutzt
        'core/notification',
        'core/templates',
        'core/localstorage',
      ],
      output: {
        format: 'iife',
        globals: {
          'core/ajax': 'core/ajax',
          'core/str': 'core/str',
          'core/notification': 'core/notification',
          'core/templates': 'core/templates',
          'core/localstorage': 'core/localstorage',
        }
      }
    }
  }
})
