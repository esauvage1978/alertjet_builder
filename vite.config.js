import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import symfonyPlugin from 'vite-plugin-symfony';

export default defineConfig({
  plugins: [
    react(),
    symfonyPlugin({
      // entrypoints.json pour pentatrion/vite-bundle
    }),
  ],
  build: {
    rollupOptions: {
      input: {
        app: './assets/main.jsx',
      },
    },
  },
});
