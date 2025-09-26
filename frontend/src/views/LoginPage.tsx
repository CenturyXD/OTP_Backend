import React, { useState } from 'react';
import { Form, Input, Button, Checkbox, Typography, message, Row, Space } from 'antd';
import { UserOutlined, LockOutlined, SunOutlined, MoonOutlined } from '@ant-design/icons';
import { useNavigate, Link } from 'react-router-dom';
import { AuthService } from '../services/AuthService';
import AuthLayout from '../layouts/AuthLayout';
import ntLogo from '../assets/02_NT-Logo-with-English-Name.png';

const { Title, Text } = Typography;

interface LoginPageProps {
    isDarkMode: boolean;
    onThemeChange: (isDark: boolean) => void;
}

const LoginPage: React.FC<LoginPageProps> = ({ isDarkMode, onThemeChange }) => {
    const [loading, setLoading] = useState(false);
    const [messageApi, contextHolder] = message.useMessage();
    const authService = new AuthService();
    const navigate = useNavigate();

    const onFinish = async (values: any) => {
        console.log('onFinish called', values);
        const { username, password } = values;
        setLoading(true);
        messageApi.loading({ content: 'กำลังเข้าสู่ระบบ...', key: 'login' });
        try {
            const result = await authService.login(username, password);
            console.log('login result', result);
            if (result.success) {
                messageApi.success({ content: result.message || 'เข้าสู่ระบบสำเร็จ!', key: 'login', duration: 2 });
                navigate('/dashboard');
            } else {
                messageApi.error({ content: result.message || 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง', key: 'login', duration: 3 });
            }
        } catch (error) {
            console.error('login error', error);
            messageApi.error({ content: 'เกิดข้อผิดพลาดในการเข้าสู่ระบบ', key: 'login', duration: 3 });
        } finally {
            setLoading(false);
        }
    };
    return (
        <AuthLayout isDarkMode={isDarkMode} onThemeChange={onThemeChange}>
            {contextHolder}
            <div style={{ maxWidth: 380, width: '100%' }}>
                <div style={{ textAlign: 'center', marginBottom: 32 }}>
                    <img src={ntLogo} alt="NT Logo" style={{ height: 40, marginBottom: 16, filter: isDarkMode ? 'brightness(0) invert(1)' : 'none' }} />
                    <Title level={2}>Welcome Back</Title>
                    <Text type="secondary">Enter your credentials to access your account</Text>
                </div>
                <Form
                    name="normal_login"
                    initialValues={{ remember: true }}
                    onFinish={onFinish}
                    size="large"
                >
                    <Form.Item
                        name="username"
                        rules={[{ required: true, message: 'Please input your Username!' }]}
                    >
                        <Input prefix={<UserOutlined />} placeholder="Username" />
                    </Form.Item>
                    <Form.Item
                        name="password"
                        rules={[{ required: true, message: 'Please input your Password!' }]}
                    >
                        <Input.Password prefix={<LockOutlined />} placeholder="Password" />
                    </Form.Item>
                    <Form.Item>
                        <Row justify="space-between">
                            <Form.Item name="remember" valuePropName="checked" noStyle>
                                <Checkbox>Remember me</Checkbox>
                            </Form.Item>

                        </Row>
                    </Form.Item>
                    <Form.Item>
                        <Button type="primary" htmlType="submit" style={{ width: '100%' }} size="large" loading={loading}>
                            Log in
                        </Button>
                    </Form.Item>
                    <Text style={{ textAlign: 'center', display: 'block' }}>
                        Don't have an account? <Link to="/register">Sign up</Link>
                    </Text>
                </Form>
            </div>
        </AuthLayout>
    );
};

export default LoginPage;