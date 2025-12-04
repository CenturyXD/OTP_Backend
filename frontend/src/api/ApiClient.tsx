import axios, { type AxiosInstance, type AxiosResponse, type AxiosError } from 'axios';
import { TokenStorage } from '../utils/TokenStorage';
type CoreIpCreationData = Omit<CoreIpData, 'id' | 'status' | 'created_at' | 'updated_at'>;
type BrkIpCreationData = Omit<BrkIpData, 'id' | 'status' | 'created_at' | 'updated_at'>;
type IntraCreationData = Omit<IntraData, 'id' | 'status' | 'created_at' | 'updated_at'>;

// --- Interfaces ---
// สร้าง Interface สำหรับข้อมูล IP เพื่อให้ TypeScript รู้จัก
// คุณสามารถย้ายไปไฟล์แยกต่างหาก (เช่น src/types/ip.ts) ได้ในอนาคต
export interface CoreIpData {
    id?: number;
    ip_address: string;
    division: string;
    contact: string;
    phone: string;
    remark?: string | null;
    created_at?: string;
    status: 'active' | 'inactive' | 'reserved' | 'maintenance';
    updater?: {
        name?: string;
    } | null;
    updated_at?: string;
}

export interface BrkIpData {
    id?: number;
    ip_address: string;
    customer: string;
    contact: string;
    phone: string;
    remark?: string | null;
    created_at?: string;
    updater?: {
        name?: string;
    } | null;
    updated_at?: string;
    status: 'active' | 'inactive' | 'reserved' | 'maintenance';
}

export interface IntraData {
    id?: number;
    ip_address: string;
    customer: string;
    contact: string;
    phone: string;
    remark?: string | null;
    created_at?: string;
    updater?: {
        name?: string;
    } | null;
    updated_at?: string;
    status: 'active' | 'inactive' | 'reserved' | 'maintenance';
}


// Interface สำหรับ Response ที่มีการแบ่งหน้าจาก Laravel
export interface PaginatedResponse<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    // ... และ property อื่นๆ จาก Laravel paginator
}



export class ApiClient {
    private instance: AxiosInstance;

    constructor() {
        this.instance = axios.create({
            baseURL: import.meta.env.VITE_API_URL,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
        });

        // Interceptor สำหรับแนบ Token ไปกับทุก Request
        this.instance.interceptors.request.use(config => {
            const token = TokenStorage.getToken();
            if (token) {
                config.headers.Authorization = `Bearer ${token}`;
            }
            return config;
        });

        // Interceptor สำหรับดักจับ Response Errors
        this.instance.interceptors.response.use(
            (response) => response,
            (error: AxiosError) => {
                if (error.response) {
                    const { status, config } = error.response;

                    if (status === 401 && !config.url?.includes('/api/login')) {
                        TokenStorage.removeToken();
                        localStorage.removeItem('userRole');
                        window.location.href = '/login';
                    }

                    if (status === 403) {
                        window.location.href = '/access-denied';
                    }
                }
                return Promise.reject(error);
            }
        );
    }

    private handleResponse(response: AxiosResponse) {
        return response.data;
    }

    private handleError(error: AxiosError) {
        if (error.response) {
            throw error.response.data;
        } else {
            throw new Error(error.message);
        }
    }

    // --- Generic Methods ---
    async get(path: string, params?: object): Promise<any> {
        try {
            const response = await this.instance.get(path, { params });
            return this.handleResponse(response);
        } catch (error) {
            this.handleError(error as AxiosError);
        }
    }

    async post(path: string, data: any): Promise<any> {
        try {
            const response = await this.instance.post(path, data);
            return this.handleResponse(response);
        } catch (error) {
            this.handleError(error as AxiosError);
        }
    }

    async put(path: string, data: any): Promise<any> {
        try {
            const response = await this.instance.put(path, data);
            return this.handleResponse(response);
        } catch (error) {
            this.handleError(error as AxiosError);
        }
    }

    async delete(path: string): Promise<any> {
        try {
            const response = await this.instance.delete(path);
            return this.handleResponse(response);
        } catch (error) {
            this.handleError(error as AxiosError);
        }
    }

    // --- Core IP Specific Methods ---

    public getCoreIps(page: number = 1, per_page: number = 5, search: string = ''): Promise<PaginatedResponse<CoreIpData>> {
        return this.get('/api/core-ips', { page, per_page, search });
    }

    public createCoreIp(data: CoreIpCreationData): Promise<CoreIpData> {
        return this.post('/api/core-ips', data);
    }

    public updateCoreIp(id: number, data: CoreIpData): Promise<CoreIpData> {
        return this.put(`/api/core-ips/${id}`, data);
    }

    public deleteCoreIp(id: number): Promise<void> {
        return this.delete(`/api/core-ips/${id}`);
    }

    // --- Brk IP Specific Methods ---
    public getBrkIps(page: number = 1, per_page: number = 5, search: string = ''): Promise<PaginatedResponse<BrkIpData>> {
        return this.get('/api/brk-ips', { page, per_page, search });
    }

    public createBrkIp(data: BrkIpCreationData): Promise<BrkIpData> {
        return this.post('/api/brk-ips', data);
    }

    public updateBrkIp(id: number, data: BrkIpData): Promise<BrkIpData> {
        return this.put(`/api/brk-ips/${id}`, data);
    }

    // --- Intra IP Specific Methods ---
    public getIntraIps(page: number = 1, per_page: number = 5, search: string = ''): Promise<PaginatedResponse<IntraData>> {
        return this.get('/api/intra-ips', { page, per_page, search })
    }

    public createIntraIps(data: IntraCreationData): Promise<IntraData> {
        return this.post('/api/intra-ips', data);
    }

    public updateIntraIps(id: number, data: IntraData): Promise<IntraData> {
        return this.put(`/api/intra-ips/${id}`, data);
    }


    // --- User Specific Methods ---
    public getUsers(page: number = 1, per_page: number = 10, search: string = ''): Promise<PaginatedResponse<any>> {
        return this.get('/api/admin/users', { page, per_page, search });
    }

    public updateUser(id: number, data: any): Promise<any> {
        return this.put(`/api/admin/users/${id}`, data);
    }

    public updateProfile(data: any): Promise<any> {
        return this.put('/api/profile', data);
    }

    public changePassword(data: { current_password: string; new_password: string; new_password_confirmation: string; }): Promise<any> {
        return this.post('/api/change-password', data);
    }

    
}