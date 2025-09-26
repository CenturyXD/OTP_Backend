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

    async login(username: string, password: string): Promise<{ success: boolean; message?: string }> {
        try {
            const response = await this.apiClient.post('/api/login', { username, password });

            if (response.success && response.access_token) {
                TokenStorage.setToken(response.access_token);
                localStorage.setItem('userRole', response.role);
                return { success: true, message: response.message  };
            }

            // คืน message จาก backend ด้วย
            return { success: false, message: response.message  };

        } catch (error: any) {
            console.error('Login failed:', error);
            return { success: false, message: error?.message };
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