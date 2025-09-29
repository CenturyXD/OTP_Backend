import React, { useState, useEffect } from 'react';
import {
    Card, Col, Row, Statistic, Table, Typography, Tag, Space, Button, App, Input, Modal, Form, Select
} from 'antd';
import { EditOutlined } from '@ant-design/icons';
import { UserService } from '../../services/UserService';
import { useDebounce } from '../../hooks/useDebounce';

const { Title, Text } = Typography;
const { Search } = Input;
const { Option } = Select;

interface User {
    id: number;
    name: string;
    username: string;
    role: 'admin' | 'user' | 'superadmin';
    status: 'active' | 'deactive';
    created_at: string;
}

const UserManagementPage: React.FC = () => {
    const { notification } = App.useApp();
    const [users, setUsers] = useState<User[]>([]);
    const [loading, setLoading] = useState(true);
    const [isModalVisible, setIsModalVisible] = useState(false);
    const [editingUser, setEditingUser] = useState<User | null>(null);
    const [form] = Form.useForm();
    const [searchTerm, setSearchTerm] = useState('');
    const debouncedSearchTerm = useDebounce(searchTerm, 500);
    const [summary, setSummary] = useState({ total: 0, admin: 0, user: 0, superadmin: 0 });
    const [pagination, setPagination] = useState({ current: 1, pageSize: 10, total: 0 });

    // Define a type for the expected response structure
    type UserServiceResponse =
        | { data: User[] }
        | { data: User[]; total: number; current_page?: number; per_page?: number }
        | { data: { data: User[]; total: number; current_page?: number; per_page?: number } };

    // Fetch users with pagination and search
    const fetchUsers = async (page: number, pageSize: number, search: string) => {
        setLoading(true);
        try {
            const response: UserServiceResponse = await UserService.fetchUsers(search, page, pageSize);

            // ตรวจสอบว่า response.data เป็น array หรือ object
            let data: User[] = [];
            let total = 0, current_page = 1, per_page = pageSize;

            if (Array.isArray(response.data)) {
                data = response.data;
                total = data.length;
            } else if (
                typeof response.data === 'object' &&
                response.data !== null &&
                'data' in response.data
            ) {
                data = (response.data as { data: User[] }).data || [];
                total = (response.data as any).total ?? data.length;
                current_page = (response.data as any).current_page ?? 1;
                per_page = (response.data as any).per_page ?? pageSize;
            }

            setUsers(data);
            setPagination({
                current: current_page,
                pageSize: per_page,
                total: total,
            });

            if (search === '') {
                setSummary({
                    total: total,
                    admin: data.filter((u: User) => u.role === 'admin').length,
                    user: data.filter((u: User) => u.role === 'user').length,
                    superadmin: data.filter((u: User) => u.role === 'superadmin').length,
                });
            }
        } catch (error) {
            notification.error({ message: 'Error', description: 'ไม่สามารถดึงข้อมูลผู้ใช้ได้' });
        } finally {
            setLoading(false);
        }
    };

    // ดึงข้อมูลเมื่อ search เปลี่ยน (debounced)
    useEffect(() => {
        fetchUsers(1, pagination.pageSize, debouncedSearchTerm);
        // eslint-disable-next-line
    }, [debouncedSearchTerm, pagination.pageSize]);

    // Modal handlers
    const showEditModal = (user: User) => {
        setEditingUser(user);
        form.setFieldsValue({
            ...user,
            status: user.status === 'active' ? 'active' : 'deactive',
        });
        setIsModalVisible(true);
    };
    const handleCancel = () => setIsModalVisible(false);

    // CRUD
    const onFinish = async (values: any) => {
        setLoading(true);
        try {
            if (editingUser) {
                await UserService.updateUser(editingUser.id, { status: values.status });
                notification.success({ message: 'Success', description: 'อัปเดตสถานะผู้ใช้สำเร็จ' });
            }
            setIsModalVisible(false);
            fetchUsers(pagination.current, pagination.pageSize, searchTerm);
        } catch (error: any) {
            if (error && error.errors) {
                notification.error({ message: 'Validation Error', description: Object.values(error.errors).flat().join('\n') });
            } else {
                notification.error({ message: 'Error', description: 'เกิดข้อผิดพลาดในการบันทึกข้อมูล' });
            }
        } finally {
            setLoading(false);
        }
    };

    // Table columns
    const columns = [
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
            title: 'Status',
            dataIndex: 'status',
            key: 'status',
            render: (status: string) => (
                <Tag color={status === 'active' ? 'green' : 'volcano'}>
                    {status === 'active' ? 'ACTIVE' : 'DEACTIVE'}
                </Tag>
            ),
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
                    <Button icon={<EditOutlined />} onClick={() => showEditModal(record)} />
                </Space>
            ),
        },
    ];

    // Table change handler (pagination, filter, sort)
    const handleTableChange = (newPagination: any) => {
        fetchUsers(newPagination.current, newPagination.pageSize, searchTerm);
    };

    // Search handler
    const handleSearchChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setSearchTerm(e.target.value);
    };

    return (
        <>
            <Title level={2}>User Management</Title>
            <Text type="secondary">Overview of all users in the system.</Text>
            <Row gutter={[16, 16]} style={{ marginTop: 24 }}>
                <Col xs={12} sm={6}><Card><Statistic title="Total Users" value={pagination.total} /></Card></Col>
                <Col xs={12} sm={6}><Card><Statistic title="Admin" value={summary.admin} valueStyle={{ color: '#1677ff' }} /></Card></Col>
                <Col xs={12} sm={6}><Card><Statistic title="User" value={summary.user} valueStyle={{ color: '#3f8600' }} /></Card></Col>
                <Col xs={12} sm={6}><Card><Statistic title="Superadmin" value={summary.superadmin} valueStyle={{ color: '#cf1322' }} /></Card></Col>
            </Row>

            <Card
                title="User Table"
                style={{ marginTop: 24 }}
                extra={
                    <Space wrap>
                        <Search
                            placeholder="Search user..."
                            value={searchTerm}
                            onChange={handleSearchChange}
                            style={{ width: 200 }}
                        />
                    </Space>
                }
            >
                <Table
                    columns={columns}
                    dataSource={users}
                    rowKey="id"
                    loading={loading}
                    bordered
                    pagination={{
                        ...pagination,
                        showSizeChanger: true,
                        pageSizeOptions: ['10', '15', '20', '50', '100'],
                        showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
                    }}
                    onChange={handleTableChange}
                />
            </Card>

            <Modal
                title="Edit User Status"
                open={isModalVisible}
                onCancel={handleCancel}
                footer={null}
            >
                <Form
                    form={form}
                    layout="vertical"
                    onFinish={onFinish}
                    style={{ marginTop: 24 }}
                    initialValues={editingUser ? { status: editingUser.status } : {}}
                >
                    {editingUser && (
                        <>
                            <Form.Item name="name" label="Name">
                                <Input disabled />
                            </Form.Item>
                            <Form.Item name="username" label="Username">
                                <Input disabled />
                            </Form.Item>
                            <Form.Item name="status" label="Status" rules={[{ required: true }]}>
                                <Select>
                                    <Option value="active">Active</Option>
                                    <Option value="deactive">Deactive</Option>
                                </Select>
                            </Form.Item>
                            <Form.Item>
                                <Button type="primary" htmlType="submit" loading={loading}>
                                    Update Status
                                </Button>
                            </Form.Item>
                        </>
                    )}
                </Form>
            </Modal>
        </>
    );
};

export default UserManagementPage;