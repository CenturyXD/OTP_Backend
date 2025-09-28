import React, { useState, useEffect } from 'react';
import { Layout, Menu, Button, Switch, Space, message } from 'antd';
import {
    DashboardOutlined,
    LogoutOutlined,
    MenuUnfoldOutlined,
    MenuFoldOutlined,
    MoonOutlined,
    SunOutlined,
    ToolOutlined,
    CrownOutlined
} from '@ant-design/icons';
import { Outlet, useNavigate, Link, useLocation } from 'react-router-dom';
import { AuthService } from '../services/AuthService';
import ntLogo from '../assets/02_NT-Logo-with-English-Name.png';
import type { MenuProps } from 'antd';

const { Header, Sider, Content } = Layout;

interface MainLayoutProps {
    isDarkMode: boolean;
    onThemeChange: (isDark: boolean) => void;
}

const MainLayout: React.FC<MainLayoutProps> = ({ isDarkMode, onThemeChange }) => {
    const [collapsed, setCollapsed] = useState(false);
    const [messageApi, contextHolder] = message.useMessage();
    const navigate = useNavigate();
    const location = useLocation();
    const authService = new AuthService();

    // --- เพิ่ม state สำหรับ openKeys ---
    const [openKeys, setOpenKeys] = useState<string[]>([]);

    useEffect(() => {
        const keys: string[] = [];
        if (location.pathname.startsWith('/noc-tool')) keys.push('sub1');
        if (location.pathname.startsWith('/admin')) keys.push('sub2');
        setOpenKeys(keys);
    }, [location.pathname]);

    const handleLogout = () => {
        authService.logout();
        messageApi.success('ออกจากระบบสำเร็จs');
        navigate('/login');
    };

    const userRole = authService.getRole();

    const items: MenuProps['items'] = [
        {
            key: '1',
            icon: <DashboardOutlined />,
            label: <Link to="/dashboard">Dashboard</Link>,
        },
        // {
        //     key: '2',
        //     icon: <TableOutlined />,
        //     label: <Link to="/tables">Tables</Link>,
        // },
        // {
        //     key: '3',
        //     icon: <UserOutlined />,
        //     label: <Link to="/profile">Profile</Link>,
        // },
        {
            key: 'sub1',
            icon: <ToolOutlined />,
            label: 'IP Tool',
            children: [
                {
                    key: '4',
                    label: <Link to="/noc-tool/ip-brk">IP BRK</Link>,
                },
                {
                    key: '6',
                    label: (
                        <a
                            href="https://whois.domaintools.com/"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            Whois Lookup
                        </a>
                    ),
                },
                {
                    key: '7',
                    label: (
                        <a
                            href="https://www.iplocation.net/ip-lookup"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            IP Location Lookup
                        </a>
                    ),
                },
                {
                    key: '8',
                    label: (
                        <a
                            href="https://ipinfo.io/"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            IP Information
                        </a>
                    ),
                },
            ],
        },
    ];

    if (userRole === 'superadmin') {
        items.push({
            key: 'sub2',
            icon: <CrownOutlined />,
            label: 'Super Admin',
            children: [
                {
                    key: '5',
                    label: <Link to="/admin/user-management">User Management</Link>,
                },
            ],
        });
    }

    // Map path กับ key
    const pathKeyMap: { [key: string]: string } = {
        '/dashboard': '1',
        '/tables': '2',
        '/profile': '3',
        '/noc-tool/ip-brk': '4',
        '/admin/user-management': '5',
    };

    // หา key จาก path จริง
    const selectedKey = Object.keys(pathKeyMap).find(path =>
        location.pathname.startsWith(path)
    ) ? pathKeyMap[location.pathname] : '1';

    return (
        <Layout style={{ minHeight: '100vh' }}>
            <Sider
                trigger={null}
                collapsible
                collapsed={collapsed}
                theme={isDarkMode ? 'dark' : 'light'}
                breakpoint="lg"
                collapsedWidth="0"
                onCollapse={(collapsed) => {
                    setCollapsed(collapsed);
                }}
            >
                <div style={{ height: 32, margin: 16, display: 'flex', alignItems: 'center', justifyContent: 'center', borderRadius: 6 }}>
                    <img src={ntLogo} alt="Logo" style={{ height: 20, filter: isDarkMode ? 'brightness(0) invert(1)' : 'none' }} />
                </div>
                <Menu
                    theme={isDarkMode ? 'dark' : 'light'}
                    mode="inline"
                    selectedKeys={[selectedKey]}
                    openKeys={openKeys}
                    onOpenChange={setOpenKeys}
                    items={items}
                />
            </Sider>
            <Layout>
                <Header style={{ padding: '0 16px', background: isDarkMode ? '#141414' : '#fff', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                    <Button
                        type="text"
                        icon={collapsed ? <MenuUnfoldOutlined /> : <MenuFoldOutlined />}
                        onClick={() => setCollapsed(!collapsed)}
                        style={{ fontSize: '16px', width: 64, height: 64 }}
                    />
                    <Space>
                        <Switch
                            checkedChildren={<MoonOutlined />}
                            unCheckedChildren={<SunOutlined />}
                            checked={isDarkMode}
                            onChange={onThemeChange}
                        />
                        <Button type="primary" icon={<LogoutOutlined />} onClick={handleLogout}>
                            Logout
                        </Button>
                    </Space>
                </Header>
                <Content style={{ margin: '24px 16px', padding: 24, minHeight: 280, overflow: 'auto', minWidth: 0 }}>
                    {contextHolder}
                    <Outlet />
                </Content>
            </Layout>
        </Layout>
    );
};

export default MainLayout;