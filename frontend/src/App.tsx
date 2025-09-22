import React, { useState, useEffect } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { ConfigProvider, theme, App as AntApp } from 'antd';
import LoginPage from './views/LoginPage';
import RegisterPage from './views/RegisterPage';
import Dashboard from './views/Dashboard';
import ProtectedRoute from './router/ProtectedRoute'; // <-- ยามด่าน 1: ตรวจสอบการ Login
import AdminRoute from './router/AdminRoute';       // <-- ยามด่าน 2: ตรวจสอบ Role (Import เข้ามา)
import MainLayout from './layouts/MainLayout';
import IpBrkPage from './views/Brkboard';
import AccessDeniedPage from './views/AccessDenied';
import UserManagementPage from './views/admin/user-management';
import NotFoundPage from './views/Notfound';


const { defaultAlgorithm, darkAlgorithm } = theme;

const App: React.FC = () => {
    const [isDarkMode, setIsDarkMode] = useState(false);

    useEffect(() => {
        const savedTheme = localStorage.getItem('theme') === 'dark';
        setIsDarkMode(savedTheme);
    }, []);

    const handleThemeChange = (isDark: boolean) => {
        setIsDarkMode(isDark);
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
    };

    return (
        <ConfigProvider
            theme={{
                algorithm: isDarkMode ? darkAlgorithm : defaultAlgorithm,
            }}
        >
            <AntApp>
                <BrowserRouter>
                    <Routes>
                        {/* --- Public Routes --- */}
                        <Route path="/login" element={<LoginPage isDarkMode={isDarkMode} onThemeChange={handleThemeChange} />} />
                        <Route path="/register" element={<RegisterPage isDarkMode={isDarkMode} onThemeChange={handleThemeChange} />} />
                        <Route path="/access-denied" element={<AccessDeniedPage />} />

                        {/* --- Protected Routes --- */}
                        <Route
                            element={
                                <ProtectedRoute>
                                    <MainLayout isDarkMode={isDarkMode} onThemeChange={handleThemeChange} />
                                </ProtectedRoute>
                            }
                        >
                            {/* Routes สำหรับผู้ใช้ทั่วไปที่ Login แล้ว */}
                            <Route index element={<Navigate to="/dashboard" replace />} />
                            <Route path="dashboard" element={<Dashboard />} />
                            <Route path="noc-tool/ip-brk" element={<IpBrkPage />} />

                            {/* --- Admin Only Routes --- */}
                            <Route element={<AdminRoute />}>
                                <Route path="admin/user-management" element={<UserManagementPage />} />
                            </Route>
                        </Route>
                        <Route path="*" element={<NotFoundPage />} />

                    </Routes>
                </BrowserRouter>
            </AntApp>
        </ConfigProvider>
    );
};

export default App;