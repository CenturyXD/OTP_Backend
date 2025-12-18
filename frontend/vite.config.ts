import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
  server: {
    host: true, // สำคัญเพื่อให้ Docker รับการเชื่อมต่อได้
    port: 5173,
    watch: {
      usePolling: true
    },
    // --- ตรวจสอบว่ามีส่วนนี้อยู่ ---
    proxy: {
      // ดักจับทุก request ที่ขึ้นต้นด้วย /api
      '/api': {
        // ส่งต่อไปยัง Nginx container
        //prod
        //target: 'http://my-nginx-proxy', 
        //dev
        target: 'http://localhost:8000',
        changeOrigin: true,
      }
    }
    // --- สิ้นสุดส่วนที่ต้องตรวจสอบ ---
  }
})