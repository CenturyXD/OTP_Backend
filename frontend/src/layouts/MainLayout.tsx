import React, { useState, useEffect } from 'react';
import { Layout, Menu, Button, Switch, Space, message, Avatar } from 'antd';
import {
    DashboardOutlined,
    LogoutOutlined,
    MenuUnfoldOutlined,
    MenuFoldOutlined,
    MoonOutlined,
    SunOutlined,
    ToolOutlined,
    CrownOutlined,
    UserOutlined,
    // FolderOpenOutlined,
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

const SIDEBAR_WIDTH = 200;

const MainLayout: React.FC<MainLayoutProps> = ({ isDarkMode, onThemeChange }) => {
    const [collapsed, setCollapsed] = useState(false);
    const [messageApi, contextHolder] = message.useMessage();
    const navigate = useNavigate();
    const location = useLocation();
    const authService = new AuthService();

    // เปิด/ปิด Submenu ตามเส้นทาง
    const [openKeys, setOpenKeys] = useState<string[]>([]);
    useEffect(() => {
        const keys: string[] = [];
        if (location.pathname.startsWith('/noc-tool')) keys.push('sub1');
        if (location.pathname.startsWith('/admin')) keys.push('sub2');
        setOpenKeys(keys);
    }, [location.pathname]);

    // ชื่อผู้ใช้สำหรับแสดงคู่กับ Avatar
    const [userName, setUserName] = useState('User');
    useEffect(() => {
        const name = localStorage.getItem('name');
        setUserName(name && name.trim() ? name : 'User');
    }, []);

    const handleLogout = () => {
        authService.logout();
        messageApi.success('ออกจากระบบสำเร็จ');
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
        //     icon: <FolderOpenOutlined />,
        //     label: <Link to="/assets">Assets</Link>,
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
                    key: '9',
                    label: <Link to="/noc-tool/ip-intranet">INTRANET</Link>
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
        '/noc-tool/ip-intranet': '9'
    };

    // หา key จาก path จริง
    const selectedKey = Object.keys(pathKeyMap).find(path =>
        location.pathname.startsWith(path)
    ) ? pathKeyMap[location.pathname] : '1';

    // Responsive sidebar width
    const getSidebarWidth = () => (collapsed ? 0 : SIDEBAR_WIDTH);

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
                width={SIDEBAR_WIDTH}
                style={{
                    position: 'fixed',
                    left: 0,
                    top: 0,
                    bottom: 0,
                    zIndex: 100,
                    height: '100vh',
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
            <Layout
                style={{
                    marginLeft: getSidebarWidth(),
                    transition: 'margin-left 0.2s',
                }}
            >
                <Header
                    style={{
                        position: 'fixed',
                        top: 0,
                        left: getSidebarWidth(),
                        right: 0,
                        zIndex: 101,
                        width: `calc(100% - ${getSidebarWidth()}px)`,
                        padding: '0 16px',
                        background: isDarkMode ? '#141414' : '#fff',
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center',
                        transition: 'left 0.2s, width 0.2s',
                    }}
                >
                    <Button
                        type="text"
                        icon={collapsed ? <MenuUnfoldOutlined /> : <MenuFoldOutlined />}
                        onClick={() => setCollapsed(!collapsed)}
                        style={{ fontSize: '16px', width: 64, height: 64 }}
                    />
                    <Space size="middle" align="center">
                        <Switch
                            checkedChildren={<MoonOutlined />}
                            unCheckedChildren={<SunOutlined />}
                            checked={isDarkMode}
                            onChange={onThemeChange}
                        />
                        <Space align="center">
                            <Avatar size="small" icon={<UserOutlined />} />
                            <span style={{ color: isDarkMode ? '#fff' : '#000', fontWeight: 500 }}>
                                {userName}
                            </span>
                        </Space>
                        <Button type="primary" icon={<LogoutOutlined />} onClick={handleLogout}>
                            Logout
                        </Button>
                    </Space>
                </Header>
                <Content
                    style={{
                        marginTop: 64,
                        padding: 24,
                        minHeight: 280,
                        overflow: 'auto',
                        minWidth: 0,
                        transition: 'margin-left 0.2s',
                    }}
                >
                    {contextHolder}
                    <Outlet />
                </Content>
            </Layout>
        </Layout>
    );
};

export default MainLayout;