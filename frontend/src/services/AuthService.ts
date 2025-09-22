import { ApiClient } from '../api/ApiClient';
import { TokenStorage } from '../utils/TokenStorage';

export class AuthService {
    private apiClient: ApiClient;

    constructor() {
        this.apiClient = new ApiClient();
    }

    async register(userData: any): Promise<{ success: boolean; errors?: any }> {
        try {
            // เรียก API endpoint /register ที่เราสร้างไว้ใน Laravel
            const response = await this.apiClient.post('/api/register', userData);
            
            // หาก API ตอบกลับมาว่าสำเร็จ
            if (response.success) {
                return { success: true };
            }
            
            // กรณีที่ไม่คาดคิดที่ success เป็น false แต่ไม่เกิด error
            return { success: false, errors: response.errors || {} };

        } catch (error: any) {
            console.error('Registration failed:', error);
            // ส่งต่อ error ที่ได้จาก ApiClient (ซึ่งควรจะมี validation errors)
            return { success: false, errors: error.errors || {} };
        }
    }

    async login(username: string, password: string): Promise<boolean> {
        try {
            // เรียก API endpoint /api/login ที่เราจะสร้างใน Laravel
            const response = await this.apiClient.post('/api/login', { username, password });

            // ตรวจสอบว่า API ตอบกลับมาว่าสำเร็จและมี token
            if (response.success && response.access_token) {
                // ถ้าสำเร็จ, บันทึก token ลงใน Storage
                TokenStorage.setToken(response.access_token);
                
                // บันทึก role ที่ได้รับมาโดยตรงลงใน localStorage
                localStorage.setItem('userRole', response.role);

                return true; // คืนค่า true เพื่อบอก LoginPage ว่าสำเร็จ
            }

            // กรณีที่ API ตอบกลับมาว่า success: false แต่ไม่เกิด error
            return false;

        } catch (error) {
            // ถ้าเกิด Network Error หรือ API ตอบกลับมาเป็น status 4xx, 5xx
            console.error('Login failed:', error);
            return false; // คืนค่า false เพื่อบอก LoginPage ว่าล้มเหลว
        }
    }

    logout(): void {
        // สามารถเพิ่มการเรียก API /logout ที่นี่ได้
        TokenStorage.removeToken();
        localStorage.removeItem('userRole'); // Clear user role on logout
        window.location.href = '/login'; // Redirect ไปหน้า login
    }

    isAuthenticated(): boolean {
        return TokenStorage.getToken() !== null;
    }

    getRole(): string | null {
        return localStorage.getItem('userRole');
    }
}