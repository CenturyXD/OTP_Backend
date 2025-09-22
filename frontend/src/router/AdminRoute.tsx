import React from 'react';
import { Navigate, Outlet } from 'react-router-dom';
import { AuthService } from '../services/AuthService';

const AdminRoute: React.FC = () => {
    const authService = new AuthService();
    const userRole = authService.getRole();

    // ตรวจสอบว่าผู้ใช้มี role เป็น 'superadmin' หรือไม่
    // เรื่องการ login ถูกตรวจสอบโดย ProtectedRoute ไปแล้ว
    if (userRole !== 'superadmin') {
        // ถ้าไม่ใช่ 'superadmin' ให้ redirect ไปยังหน้า Access Denied
        return <Navigate to="/access-denied" replace />;
    }

    // ถ้าเป็น 'superadmin' ให้แสดง Component ที่อยู่ภายใต้ Route นี้ (เช่น UserManagementPage)
    return <Outlet />;
};

export default AdminRoute;