# ใช้ Node.js เป็น Base Image
FROM node:22-alpine

# ตั้งค่าโฟลเดอร์ทำงานใน Container
WORKDIR /app

# คัดลอกไฟล์ที่จำเป็นสำหรับการติดตั้ง dependencies
COPY package*.json ./

# ติดตั้ง dependencies
RUN npm install

# คัดลอกโค้ดทั้งหมดไปยัง container
COPY . .

# Build โปรเจกต์ (สำหรับ production)
RUN npm run build

# ใช้ Nginx เป็น web server
FROM nginx:stable-alpine

# คัดลอก build ไปยัง Nginx
COPY --from=0 /app/dist /usr/share/nginx/html

# คัดลอกไฟล์ default.conf ไปยัง Nginx
COPY nginx/default.conf /etc/nginx/conf.d/default.conf


# เปิด Port 80
EXPOSE 80

# Start Nginx
CMD ["nginx", "-g", "daemon off;"]