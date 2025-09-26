import { ApiClient } from '../api/ApiClient';

const apiClient = new ApiClient();

export const UserService = {
    async fetchUsers(search: string = '', page: number = 1, pageSize: number = 10) {
        return apiClient.getUsers(page, pageSize, search);
    },
    async updateUser(id: number, data: any) {
        return apiClient.updateUser(id, data);
    },
};