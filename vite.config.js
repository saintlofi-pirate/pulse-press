import { defineConfig } from 'vite';
import preact from '@preact/preset-vite';
import { resolve } from 'node:path';

export default defineConfig({
  plugins: [preact()],
  build: {
    outDir: 'dist',
    manifest: true,
    emptyOutDir: true,
    rollupOptions: {
      input: {
        widget: resolve(__dirname, 'resources/widget/index.ts'),
      },
      output: {
        entryFileNames: 'js/[name].[hash].js',
        chunkFileNames: 'js/[name].[hash].js',
        assetFileNames: 'assets/[name].[hash].[ext]',
      },
    },
  },
});
