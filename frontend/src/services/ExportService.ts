import * as XLSX from 'xlsx';

// กำหนด Type ของข้อมูลที่จะรับเข้ามา
// เป็น Array ของ Object ที่มี key เป็น string และ value เป็นอะไรก็ได้
type ExportData = Record<string, any>[];

class ExportService {
    /**
     * ฟังก์ชันสำหรับแปลงข้อมูล (Array of objects) ให้เป็นไฟล์ Excel แล้วดาวน์โหลด
     * @param data ข้อมูลที่ต้องการ Export
     * @param fileName ชื่อไฟล์ (ไม่ต้องใส่นามสกุล)
     */
    public toExcel(data: ExportData, fileName: string): void {
        if (!data || data.length === 0) {
            console.warn("No data provided to export.");
            return;
        }

        // สร้าง Worksheet จากข้อมูล
        const worksheet = XLSX.utils.json_to_sheet(data);
        // สร้าง Workbook ใหม่
        const workbook = XLSX.utils.book_new();
        // เพิ่ม Worksheet เข้าไปใน Workbook
        XLSX.utils.book_append_sheet(workbook, worksheet, 'Data');

        // สร้างชื่อไฟล์สุดท้ายพร้อมวันที่
        const finalFileName = `${fileName}_${new Date().toISOString().slice(0, 10)}.xlsx`;

        // สั่งให้ดาวน์โหลดไฟล์
        XLSX.writeFile(workbook, finalFileName);
    }
}

// Export instance ของ service เพื่อให้เรียกใช้งานได้ทันที
export const exportService = new ExportService();