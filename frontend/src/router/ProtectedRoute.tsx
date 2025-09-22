import React from 'react';
import { Navigate } from 'react-router-dom';
import { AuthService } from '../services/AuthService';

interface ProtectedRouteProps {
    children: React.ReactElement;
}

const ProtectedRoute: React.FC<ProtectedRouteProps> = ({ children }) => {
    const authService = new AuthService();

    // ตรวจสอบว่าผู้ใช้ได้ Login แล้วหรือยัง (โดยดูว่ามี token ใน storage หรือไม่)
    if (!authService.isAuthenticated()) {
        // ถ้ายัง, ให้ redirect ไปที่หน้า /login
        return <Navigate to="/login" replace />;
    }

    // ถ้า Login แล้ว, ให้แสดง component ที่ถูกส่งเข้ามา (ในที่นี้คือ Dashboard)
    return children;
};

export default ProtectedRoute;