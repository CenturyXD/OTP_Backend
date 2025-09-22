import React from 'react';
import { Row, Col, Switch } from 'antd';
import { SunOutlined, MoonOutlined } from '@ant-design/icons';
import loginImage from '../assets/login_screen_low-CTb6gG_l.webp';

interface AuthLayoutProps {
    children: React.ReactNode;
    isDarkMode: boolean;
    onThemeChange: (isDark: boolean) => void;
}

const AuthLayout: React.FC<AuthLayoutProps> = ({ children, isDarkMode, onThemeChange }) => {
    return (
        // 1. ย้าย Switch ออกมานอก Row/Col
        <>
            <Switch
                checkedChildren={<MoonOutlined />}
                unCheckedChildren={<SunOutlined />}
                checked={isDarkMode}
                onChange={onThemeChange}
                style={{
                    // 2. เปลี่ยนเป็น position: 'fixed'
                    position: 'fixed',
                    top: 24,
                    right: 24,
                    // 3. เพิ่ม zIndex เพื่อให้ลอยอยู่เหนือทุกอย่าง
                    zIndex: 1000,
                }}
            />
            <Row style={{ minHeight: '100vh', backgroundColor: isDarkMode ? '#141414' : '#fff' }}>
                {/* Column ซ้ายสำหรับฟอร์ม */}
                <Col xs={24} sm={24} md={12} lg={10} xl={8} style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '24px' }}>
                    {/* ไม่ต้องมี Switch ที่นี่แล้ว */}
                    {children}
                </Col>

                {/* Column ขวาสำหรับรูปภาพ */}
                <Col xs={0} sm={0} md={12} lg={14} xl={16} style={{
                    backgroundImage: `url(${loginImage})`,
                    backgroundSize: 'cover',
                    backgroundPosition: 'center'
                }} />
            </Row>
        </>
    );
};

export default AuthLayout;