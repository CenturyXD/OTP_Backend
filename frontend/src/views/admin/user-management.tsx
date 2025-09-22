import React, { useState, useEffect } from 'react';
import {
    Table,
    Button,
    Modal,
    Form,
    Input,
    Select,
    Space,
    Popconfirm,
    message,
    Typography,
    Tag
} from 'antd';
import { PlusOutlined, EditOutlined, DeleteOutlined } from '@ant-design/icons';
import { ApiClient } from '../../api/ApiClient'; // 1. เปิดใช้งาน ApiClient

const { Title } = Typography;
const { Option } = Select;

// กำหนด Type สำหรับข้อมูล User
interface User {
    id: number;
    name: string;
    username: string;
    role: 'admin' | 'user' | 'superadmin';
    created_at: string;
}

// 2. ลบข้อมูลจำลอง (Mock Data) ออกไป

const UserManagementPage: React.FC = () => {
    const [users, setUsers] = useState<User[]>([]); // 3. ตั้งค่า State เริ่มต้นเป็น Array ว่าง
    const [loading, setLoading] = useState(true); // 4. ตั้งค่า loading เป็น true เพื่อรอ API
    const [isModalVisible, setIsModalVisible] = useState(false);
    const [editingUser, setEditingUser] = useState<User | null>(null);
    const [form] = Form.useForm();
    const apiClient = new ApiClient(); // 5. สร้าง instance ของ ApiClient

    // 6. แก้ไขฟังก์ชัน fetchUsers ให้เรียก API จริง
    const fetchUsers = async () => {
        setLoading(true);
        try {
            // Laravel pagination ส่งข้อมูลมาใน key 'data'
            const response = await apiClient.get('/api/admin/users');
            setUsers(response.data);
        } catch (error) {
            // Error 403 จะถูกจัดการโดย Interceptor ใน ApiClient แล้ว
            // ที่นี่จะแสดง error กรณีอื่นๆ
            message.error('ไม่สามารถดึงข้อมูลผู้ใช้ได้');
        } finally {
            setLoading(false);
        }
    };

    // 7. เปิดใช้งาน useEffect เพื่อให้ดึงข้อมูลเมื่อ Component โหลด
    useEffect(() => {
        fetchUsers();
    }, []);

    // --- Handlers สำหรับจัดการ Modal และ Form ---
    const showAddModal = () => {
        setEditingUser(null);
        form.resetFields();
        setIsModalVisible(true);
    };

    const showEditModal = (user: User) => {
        setEditingUser(user);
        form.setFieldsValue({
            ...user,
            password: '', // ไม่แสดงรหัสผ่านเดิม
        });
        setIsModalVisible(true);
    };

    const handleCancel = () => {
        setIsModalVisible(false);
    };

    // 8. แก้ไข onFinish ให้เรียก API จริง
    const onFinish = async (values: any) => {
        // ถ้าไม่มีการกรอกรหัสผ่าน ให้ลบ key ออกไปเพื่อไม่ให้ส่งไปอัปเดต
        if (!values.password) {
            delete values.password;
            delete values.password_confirmation;
        }

        try {
            if (editingUser) {
                // --- โหมดแก้ไข ---
                await apiClient.put(`/api/admin/users/${editingUser.id}`, values);
                message.success('อัปเดตข้อมูลผู้ใช้สำเร็จ');
            } else {
                // --- โหมดเพิ่มใหม่ ---
                await apiClient.post('/api/admin/users', values);
                message.success('เพิ่มผู้ใช้ใหม่สำเร็จ');
            }
            setIsModalVisible(false);
            fetchUsers(); // โหลดข้อมูลใหม่หลังบันทึกสำเร็จ
        } catch (error: any) {
            // จัดการ Validation Error จาก Laravel
            if (error && error.errors) {
                const errorMessages = Object.values(error.errors).flat().join('\n');
                message.error(errorMessages);
            } else {
                message.error('เกิดข้อผิดพลาดในการบันทึกข้อมูล');
            }
        }
    };

    // 9. แก้ไข handleDelete ให้เรียก API จริง
    const handleDelete = async (userId: number) => {
        try {
            await apiClient.delete(`/api/admin/users/${userId}`);
            message.success('ลบผู้ใช้สำเร็จ');
            fetchUsers(); // โหลดข้อมูลใหม่หลังลบสำเร็จ
        } catch (error) {
            message.error('ไม่สามารถลบผู้ใช้ได้');
        }
    };

    // --- กำหนดคอลัมน์สำหรับตาราง (เหมือนเดิม) ---
    const columns = [
        { title: 'ID', dataIndex: 'id', key: 'id', sorter: (a: User, b: User) => a.id - b.id },
        { title: 'Name', dataIndex: 'name', key: 'name' },
        { title: 'Username', dataIndex: 'username', key: 'username' },
        {
            title: 'Role',
            dataIndex: 'role',
            key: 'role',
            render: (role: string) => {
                let color = 'default';
                if (role === 'superadmin') color = 'red';
                if (role === 'admin') color = 'blue';
                if (role === 'user') color = 'green';
                return <Tag color={color}>{role.toUpperCase()}</Tag>;
            },
        },
        {
            title: 'Created At',
            dataIndex: 'created_at',
            key: 'created_at',
            render: (date: string) => new Date(date).toLocaleDateString('th-TH'),
        },
        {
            title: 'Actions',
            key: 'actions',
            render: (_: any, record: User) => (
                <Space size="middle">
                    <Button icon={<EditOutlined />} onClick={() => showEditModal(record)}>
                        Edit
                    </Button>
                    <Popconfirm
                        title="คุณแน่ใจหรือไม่ที่จะลบผู้ใช้นี้?"
                        onConfirm={() => handleDelete(record.id)}
                        okText="Yes"
                        cancelText="No"
                    >
                        <Button danger icon={<DeleteOutlined />}>
                            Delete
                        </Button>
                    </Popconfirm>
                </Space>
            ),
        },
    ];

    return (
        <div>
            <Title level={2}>User Management</Title>
            <Button
                type="primary"
                icon={<PlusOutlined />}
                onClick={showAddModal}
                style={{ marginBottom: 16 }}
            >
                Add User
            </Button>
            <Table
                columns={columns}
                dataSource={users}
                rowKey="id"
                loading={loading}
                bordered
            />

            <Modal
                title={editingUser ? 'Edit User' : 'Add New User'}
                open={isModalVisible}
                onCancel={handleCancel}
                footer={null}
            >
                <Form form={form} layout="vertical" onFinish={onFinish} style={{ marginTop: 24 }}>
                    <Form.Item name="name" label="Name" rules={[{ required: true }]}>
                        <Input />
                    </Form.Item>
                    <Form.Item name="username" label="Username" rules={[{ required: true }]}>
                        <Input />
                    </Form.Item>
                    <Form.Item name="role" label="Role" rules={[{ required: true }]}>
                        <Select>
                            <Option value="admin">Admin</Option>
                            <Option value="user">User</Option>
                        </Select>
                    </Form.Item>
                    <Form.Item
                        name="password"
                        label={editingUser ? 'New Password (Optional)' : 'Password'}
                        rules={[{ required: !editingUser }]}
                    >
                        <Input.Password />
                    </Form.Item>
                    <Form.Item
                        name="password_confirmation"
                        label="Confirm Password"
                        dependencies={['password']}
                        rules={[
                            ({ getFieldValue }) => ({
                                validator(_, value) {
                                    if (!getFieldValue('password') || getFieldValue('password') === value) {
                                        return Promise.resolve();
                                    }
                                    return Promise.reject(new Error('The two passwords do not match!'));
                                },
                            }),
                        ]}
                    >
                        <Input.Password />
                    </Form.Item>
                    <Form.Item>
                        <Button type="primary" htmlType="submit" loading={loading}>
                            {editingUser ? 'Update' : 'Create'}
                        </Button>
                    </Form.Item>
                </Form>
            </Modal>
        </div>
    );
};

export default UserManagementPage;