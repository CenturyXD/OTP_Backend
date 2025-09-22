import { ApiClient, type CoreIpData,type BrkIpData,  type PaginatedResponse } from '../api/ApiClient';

export type CoreIpCreationData = Omit<CoreIpData, 'id' | 'status' | 'created_at' | 'updated_at'>;

export type BrkIpCreationData = Omit<BrkIpData, 'id' | 'status' | 'created_at' | 'updated_at'>;


export class IpManagementService {
    private apiClient: ApiClient;

    constructor() {
        this.apiClient = new ApiClient();
    }

    /**
     * ดึงข้อมูล Core IP แบบแบ่งหน้า
     */
    public getCoreIps(page: number, pageSize: number, search: string): Promise<PaginatedResponse<CoreIpData>> {
        // 2. ส่ง search ต่อไปให้ apiClient
        return this.apiClient.getCoreIps(page, pageSize, search);
    }

    public createCoreIp(data: CoreIpCreationData): Promise<CoreIpData> {
        return this.apiClient.createCoreIp(data);
    }

    public updateCoreIp(id: number, data: CoreIpData): Promise<CoreIpData> {
        return this.apiClient.updateCoreIp(id, data);
    }

    public getBrkIps(page: number, pageSize: number, search: string): Promise<PaginatedResponse<BrkIpData>> {
        return this.apiClient.getBrkIps(page, pageSize, search);
    }

    public createBrkIp(data: BrkIpCreationData): Promise<BrkIpData> {
        return this.apiClient.createBrkIp(data);
    }

    public updateBrkIp(id: number, data: BrkIpData): Promise<BrkIpData> {
        return this.apiClient.updateBrkIp(id, data);
    }
    /**
     * ดึงข้อมูล Brk IP แบบแบ่งหน้า
     */
    

}