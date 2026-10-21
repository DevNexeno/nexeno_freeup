/**
 * SPDX-FileCopyrightText: 2026 Nexeno contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig(() => {
    return {
        plugins: [vue()],
        define: {
            'process.env.NODE_ENV': JSON.stringify('production'),
            appName: JSON.stringify('nexeno_freeup'),
            appVersion: JSON.stringify('1.0.0')
        },
        build: {
            outDir: '.',
            emptyOutDir: false,
            lib: {
                entry: path.resolve(__dirname, 'src/main.js'),
                name: 'NexenoFreeUpApp',
                formats: ['iife'],
                fileName: () => 'js/nexeno_freeup.js'
            },
            rollupOptions: {
                output: {
                    assetFileNames: (assetInfo) => {
                        if (assetInfo.name && assetInfo.name.endsWith('.css')) {
                            return 'css/nexeno_freeup.css';
                        }
                        return 'assets/[name].[ext]';
                    }
                }
            }
        }
    };
});
