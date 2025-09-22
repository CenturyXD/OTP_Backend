import React, { useState } from 'react';
import { Form, Input, Button, Typography, message } from 'antd';
import { UserOutlined, LockOutlined, MailOutlined, IdcardOutlined } from '@ant-design/icons';
import { useNavigate, Link } from 'react-router-dom';
import { AuthService } from '../services/AuthService';
import AuthLayout from '../layouts/AuthLayout';
import ntLogo from '../assets/02_NT-Logo-with-English-Name.png';

const { Title, Text } = Typography;

interface RegisterPageProps {
    isDarkMode: boolean;
    onThemeChange: (isDark: boolean) => void;
}

const RegisterPage: React.FC<RegisterPageProps> = ({ isDarkMode, onThemeChange }) => {
    const [loading, setLoading] = useState(false);
    const [messageApi, contextHolder] = message.useMessage();
    const authService = new AuthService();
    const navigate = useNavigate();
    const [form] = Form.useForm();

    const onFinish = async (values: any) => {
        setLoading(true);
        messageApi.loading({ content: 'กำลังสร้างบัญชี...', key: 'register' });

        const userData = {
            name: values.name,
            username: values.username,
            email: values.email,
            password: values.password,
        };

        try {
            const result = await authService.register(userData);
            if (result.success) {
                messageApi.success({ content: 'สร้างบัญชีสำเร็จ! กรุณาเข้าสู่ระบบ', key: 'register', duration: 3 });
                navigate('/login');
            } else {
                let errorMessage = 'การสร้างบัญชีล้มเหลว, กรุณาลองใหม่อีกครั้ง';
                if (result.errors && Object.keys(result.errors).length > 0) {
                    const firstErrorKey = Object.keys(result.errors)[0];
                    errorMessage = result.errors[firstErrorKey][0];
                }
                messageApi.error({ content: errorMessage, key: 'register', duration: 4 });
            }
        } catch (error) {
            messageApi.error({ content: 'เกิดข้อผิดพลาดในการเชื่อมต่อ', key: 'register', duration: 4 });
        } finally {
            setLoading(false);
        }
    };

    return (
        <AuthLayout isDarkMode={isDarkMode} onThemeChange={onThemeChange}>
            {contextHolder}
            <div style={{ maxWidth: 400, width: '100%' }}>
                <div style={{ textAlign: 'center', marginBottom: 32 }}>
                    <img src={ntLogo} alt="NT Logo" style={{ height: 40, marginBottom: 16, filter: isDarkMode ? 'brightness(0) invert(1)' : 'none' }} />
                    <Title level={2}>Create Account</Title>
                    <Text type="secondary">Join us today! Create your account to get started.</Text>
                </div>
                <Form
                    form={form}
                    name="register"
                    onFinish={onFinish}
                    size="large"
                    scrollToFirstError
                >
                    <Form.Item
                        name="name"
                        rules={[{ required: true, message: 'Please input your Name!', whitespace: true }]}
                    >
                        <Input prefix={<IdcardOutlined />} placeholder="Full Name" />
                    </Form.Item>
                    <Form.Item
                        name="username"
                        rules={[{ required: true, message: 'Please input your Username!', whitespace: true }]}
                    >
                        <Input prefix={<UserOutlined />} placeholder="Username" />
                    </Form.Item>
                    <Form.Item
                        name="email"
                        rules={[
                            { type: 'email', message: 'The input is not valid E-mail!' },
                            { required: true, message: 'Please input your E-mail!' },
                        ]}
                    >
                        <Input prefix={<MailOutlined />} placeholder="Email" />
                    </Form.Item>
                    <Form.Item
                        name="password"
                        rules={[{ required: true, message: 'Please input your password!' }]}
                        hasFeedback
                    >
                        <Input.Password prefix={<LockOutlined />} placeholder="Password" />
                    </Form.Item>
                    <Form.Item
                        name="confirm"
                        dependencies={['password']}
                        hasFeedback
                        rules={[
                            { required: true, message: 'Please confirm your password!' },
                            ({ getFieldValue }) => ({
                                validator(_, value) {
                                    if (!value || getFieldValue('password') === value) {
                                        return Promise.resolve();
                                    }
                                    return Promise.reject(new Error('The two passwords that you entered do not match!'));
                                },
                            }),
                        ]}
                    >
                        <Input.Password prefix={<LockOutlined />} placeholder="Confirm Password" />
                    </Form.Item>
                    <Form.Item>
                        <Button type="primary" htmlType="submit" style={{ width: '100%' }} loading={loading}>
                            Register
                        </Button>
                    </Form.Item>
                    <Text style={{ textAlign: 'center', display: 'block' }}>
                        Already have an account? <Link to="/login">Log in</Link>
                    </Text>
                </Form>
            </div>
        </AuthLayout>
    );
};

export default RegisterPage;