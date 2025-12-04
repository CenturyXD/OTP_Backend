import React, { useEffect, useMemo, useState } from 'react';
import {
  Typography,
  Card,
  Layout,
  Row,
  Col,
  Avatar,
  Button,
  Form,
  Input,
  message,
} from 'antd';
import { UserOutlined } from '@ant-design/icons';

import { ApiClient } from '../api/ApiClient';

const apiClient = new ApiClient();

const Settings: React.FC = () => {
  const { Title, Text } = Typography;
  const [form] = Form.useForm();
  const [pwdForm] = Form.useForm();
  const [pwdLoading, setPwdLoading] = useState(false);

  // State สำหรับข้อมูลโปรไฟล์จาก localStorage (เฉพาะ name, email, role)
  const [profileData, setProfileData] = useState({
    name: '',
    email: '',
    role: '',
  });

  // ดึงข้อมูลจาก localStorage เมื่อโหลดหน้า
  useEffect(() => {
    const name = localStorage.getItem('name') || '';
    const email = localStorage.getItem('email') || '';
    const role =
      localStorage.getItem('userRole') || localStorage.getItem('role') || '';

    const nextData = { name, email, role };
    setProfileData(nextData);
    form.setFieldsValue(nextData);
  }, [form]);

  const initials = useMemo(() => {
    return (profileData.name?.trim()?.slice(0, 2) || '').toUpperCase();
  }, [profileData]);

  interface FormValues {
    name: string;
    email: string;
    role: string;
  }

  // ฟังก์ชันสำหรับอัปเดตโปรไฟล์
  const onFinish = async (values: FormValues) => {
    try {
      const response = await apiClient.updateProfile({
        name: values.name,
        email: values.email,
        role: values.role, 
      });

      if (response.success) {
        // อัปเดต localStorage
        localStorage.setItem('name', response.user.name || '');
        localStorage.setItem('email', response.user.email || '');
        localStorage.setItem('role', response.user.role || '');

        // อัปเดต state
        const updatedProfile = response.user;
        setProfileData(updatedProfile);
        form.setFieldsValue(updatedProfile);

        message.success('Profile updated successfully');

        window.location.reload(); 
      } else {
        message.error(response.message || 'Failed to update profile');
      }
    } catch (error) {
      console.error(error);
      message.error('An error occurred while updating the profile');
    }
  };

  // ฟังก์ชันสำหรับเปลี่ยนรหัสผ่าน
  const onFinishPassword = async (values: {
    currentPassword: string;
    newPassword: string;
    confirmNewPassword: string;
  }) => {
    try {
      setPwdLoading(true);

      const response = await apiClient.changePassword({
        current_password: values.currentPassword,
        new_password: values.newPassword,
        new_password_confirmation: values.confirmNewPassword,
      });

      if (response.success) {
        message.success('Password updated successfully');
        pwdForm.resetFields(); // รีเซ็ตฟอร์มหลังเปลี่ยนรหัสผ่านสำเร็จ
      } else {
        message.error(response.message || 'Failed to update password');
      }
    } catch (error) {
      console.error(error);
      message.error('An error occurred while updating the password');
    } finally {
      setPwdLoading(false);
    }
  };

  return (
    <>
      <Title level={2}>Settings</Title>
      <Layout style={{ background: 'transparent', marginTop: 16 }}>
        <Row gutter={24} wrap={false}>
          <Col flex="auto">
            {/* Public Profile */}
            <Card style={{ borderRadius: 12 }}>
              <Title level={4} style={{ marginBottom: 8 }}>
                Public profile
              </Title>

              <Row align="middle" gutter={16} style={{ marginTop: 16, marginBottom: 24 }}>
                <Col>
                  <Avatar size={96} icon={<UserOutlined />}>
                    {profileData.name ? initials : null}
                  </Avatar>
                </Col>
              </Row>

              <Form
                form={form}
                layout="vertical"
                initialValues={profileData}
                onFinish={onFinish}
              >
                <Form.Item label="Name" name="name" rules={[{ required: true }]}>
                  <Input placeholder={profileData.name || 'Your name'} />
                </Form.Item>

                <Form.Item label="Email" name="email" rules={[{ type: 'email' }]}>
                  <Input placeholder={profileData.email || 'email@private.com'} />
                </Form.Item>

                <Form.Item label="Role" name="role">
                  <Input placeholder={profileData.role || 'Your role'} />
                </Form.Item>

                <Row justify="end">
                  <Col>
                    <Button type="primary" htmlType="submit">
                      Save changes
                    </Button>
                  </Col>
                </Row>
              </Form>
            </Card>

            {/* Change Password */}
            <Card style={{ borderRadius: 12, marginTop: 16 }}>
              <Title level={4} style={{ marginBottom: 8 }}>
                Change password
              </Title>
              <Text type="secondary">
                Enter your current password to set a new one.
              </Text>

              <Form
                form={pwdForm}
                layout="vertical"
                onFinish={onFinishPassword}
                style={{ marginTop: 16 }}
              >
                <Form.Item
                  label="Current password"
                  name="currentPassword"
                  rules={[{ required: true, message: 'Please enter current password' }]}
                >
                  <Input.Password autoComplete="current-password" />
                </Form.Item>

                <Form.Item
                  label="New password"
                  name="newPassword"
                  rules={[
                    { required: true, message: 'Please enter new password' },
                    { min: 8, message: 'Use at least 8 characters' },
                  ]}
                  hasFeedback
                >
                  <Input.Password autoComplete="new-password" />
                </Form.Item>

                <Form.Item
                  label="Confirm new password"
                  name="confirmNewPassword"
                  dependencies={['newPassword']}
                  hasFeedback
                  rules={[
                    { required: true, message: 'Please confirm new password' },
                    ({ getFieldValue }) => ({
                      validator(_, value) {
                        if (!value || getFieldValue('newPassword') === value) {
                          return Promise.resolve();
                        }
                        return Promise.reject(new Error('Passwords do not match'));
                      },
                    }),
                  ]}
                >
                  <Input.Password autoComplete="new-password" />
                </Form.Item>

                <Row justify="end">
                  <Col>
                    <Button type="primary" htmlType="submit" loading={pwdLoading}>
                      Update password
                    </Button>
                  </Col>
                </Row>
              </Form>
            </Card>
          </Col>
        </Row>
      </Layout>
    </>
  );
};

export default Settings;