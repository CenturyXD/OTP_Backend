# ใช้ Node.js เวอร์ชั่น LTS (Long-Term Support)
FROM node:20-alpine

# ตั้งค่า working directory ภายใน container
WORKDIR /app

# Copy package.json และ package-lock.json
COPY package*.json ./

# ติดตั้ง dependencies
RUN npm install

# Copy โค้ดทั้งหมดในโปรเจกต์
COPY . .

# เปิด port 5173 สำหรับ Vite
EXPOSE 5173

# รัน development server
CMD ["npm", "run", "dev", "--", "--host"]