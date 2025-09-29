import React, { useState, useEffect } from 'react';
import { Card, Col, Row, Statistic, Table, Typography, Tag, Space, Button, App, Input, Modal } from 'antd';
import { ArrowUpOutlined, ArrowDownOutlined, EditOutlined, PlusOutlined, ExclamationCircleFilled } from '@ant-design/icons';
import { IpManagementService } from '../services/IpManagementService';
import type { BrkIpData } from '../api/ApiClient';
import type { TableProps } from 'antd';
import { useDebounce } from '../hooks/useDebounce';
import IpFormModal, { type IpBrkFormData } from '../components/BrkIpFormModal';
import { exportService } from '../services/ExportService';

const { Title, Text } = Typography;
const { Search } = Input;

const ipService = new IpManagementService();

// Interface สำหรับเก็บข้อมูลสรุป
interface IpSummary {
    active: number;
    inactive: number;
    reserved: number;
    maintenance: number;
}

const IpBrkPage: React.FC = () => {
    const { notification } = App.useApp();

    // --- States ---
    const [data, setData] = useState<BrkIpData[]>([]);
    const [loading, setLoading] = useState(false);
    const [isExporting, setIsExporting] = useState(false);
    const [pagination, setPagination] = useState({ current: 1, pageSize: 15, total: 0 });
    const [dbTotal, setDbTotal] = useState(0);
    const [summary, setSummary] = useState<IpSummary>({ active: 0, inactive: 0, reserved: 0, maintenance: 0 });
    const [searchTerm, setSearchTerm] = useState('');
    const debouncedSearchTerm = useDebounce(searchTerm, 500);

    const [isModalVisible, setIsModalVisible] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [editingRecord, setEditingRecord] = useState<BrkIpData | null>(null);

    // --- Data Fetching ---
    const fetchData = async (page: number, pageSize: number, search: string) => {
        setLoading(true);
        try {
            const response = await ipService.getBrkIps(page, pageSize, search);
            setData(response.data);
            setPagination({ current: response.current_page, pageSize: response.per_page, total: response.total });
            if (search === '') {
                setDbTotal(response.total);
            }
        } catch (error) {
            console.error("Failed to fetch Bangrak IPs:", error);
            notification.error({ message: 'Fetch Failed', description: 'Could not fetch data from the server.' });
        } finally {
            setLoading(false);
        }
    };

    const fetchSummaryData = async () => {
        try {
            const response = await ipService.getBrkIps(1, dbTotal > 0 ? dbTotal : 10000, '');
            const summaryCounts = response.data.reduce((acc, ip) => {
                const status = ip.status as keyof IpSummary;
                if (acc.hasOwnProperty(status)) {
                    acc[status]++;
                }
                return acc;
            }, { active: 0, inactive: 0, reserved: 0, maintenance: 0 });
            setSummary(summaryCounts);
        } catch (error) {
            console.error("Failed to fetch summary data:", error);
        }
    };

    // --- Effects ---
    useEffect(() => {
        fetchData(pagination.current, pagination.pageSize, debouncedSearchTerm);
    }, [debouncedSearchTerm]);

    useEffect(() => {
        fetchData(1, 15, '');
    }, []);

    useEffect(() => {
        if (dbTotal > 0) {
            fetchSummaryData();
        }
    }, [dbTotal]);

    // --- Handlers ---
    const handleTableChange = (newPagination: any) => {
        fetchData(newPagination.current, newPagination.pageSize, searchTerm);
    };

    const handleAdd = () => {
        setEditingRecord(null);
        setIsModalVisible(true);
    };

    const handleEdit = (record: BrkIpData) => {
        setEditingRecord(record);
        setIsModalVisible(true);
    };

    const handleCancel = () => {
        setIsModalVisible(false);
        setEditingRecord(null);
    };

    const handleFormFinish = async (values: IpBrkFormData) => {
        const action = editingRecord && editingRecord.id !== undefined
            ? ipService.updateBrkIp(editingRecord.id, { ...editingRecord, ...values })
            : ipService.createBrkIp(values);
        const successMessage = editingRecord ? 'IP address has been updated successfully.' : 'New IP address has been added successfully.';

        setIsSubmitting(true);
        try {
            await action;
            notification.success({ message: 'Success', description: successMessage });
            handleCancel();
            await fetchData(editingRecord ? pagination.current : 1, pagination.pageSize, '');
            await fetchSummaryData();
        } catch (error: any) {
            console.error("Action failed:", error);
            notification.error({ message: 'Action Failed', description: error.message || 'An unexpected error occurred.' });
        } finally {
            setIsSubmitting(false);
        }
    };

    const exportData = async (dataPromise: Promise<any>, type: 'All' | 'Current') => {
        setIsExporting(true);
        notification.info({ message: 'Exporting...', description: `Preparing to export ${type.toLowerCase()} items. Please wait.`, duration: 2 });
        try {
            const response = await dataPromise;
            if (response.data.length === 0) {
                notification.info({ message: 'No Data', description: 'There is no data to export for the selected view.' });
                return;
            }
            const dataToExport = response.data.map((item: BrkIpData) => ({
                'IP Address': item.ip_address,
                'Customer': item.customer,
                'Contact': item.contact,
                'Phone': item.phone,
                'Remark': item.remark,
                'Status': item.status,
                'Updated By': item.updater?.name || 'N/A',
                'Updated At': item.updated_at ? new Date(item.updated_at).toLocaleString('th-TH') : '',
            }));
            exportService.toExcel(dataToExport, `Bangrak_IPs_Export_${type}`);
            notification.success({ message: 'Export Successful', description: `Successfully exported ${dataToExport.length} items.` });
        } catch (error) {
            console.error("Failed to export data:", error);
            notification.error({ message: 'Export Failed', description: 'Could not export data.' });
        } finally {
            setIsExporting(false);
        }
    };

    const handleExportExcel = () => {
        Modal.confirm({
            title: 'Choose Export Option',
            icon: <ExclamationCircleFilled />,
            content: 'Do you want to export all data or only the data currently displayed?',
            okText: `Export All (${dbTotal} items)`,
            okType: 'primary',
            cancelText: 'Cancel',
            footer: (_, { OkBtn, CancelBtn }) => (
                <>
                    <CancelBtn />
                    <Button
                        disabled={data.length === 0}
                        onClick={() => {
                            Modal.destroyAll();
                            exportData(Promise.resolve({ data }), 'Current');
                        }}
                    >
                        {`Export Current View (${data.length} items)`}
                    </Button>
                    <OkBtn />
                </>
            ),
            onOk: () => exportData(ipService.getBrkIps(1, dbTotal > 0 ? dbTotal : 10000, ''), 'All'),
            onCancel: () => console.log('Export cancelled'),
        });
    };

    // --- Table Columns ---
    const columns: TableProps<BrkIpData>['columns'] = [
        { title: 'IP ADDRESS', dataIndex: 'ip_address', key: 'ip_address', fixed: 'left', width: 150 },
        { title: 'customer', dataIndex: 'customer', key: 'customer', width: 200 },
        { title: 'CONTACT', dataIndex: 'contact', key: 'contact', width: 200 },
        { title: 'PHONE', dataIndex: 'phone', key: 'phone', width: 150 },
        { title: 'REMARK', dataIndex: 'remark', key: 'remark', width: 250 },
        {
            title: 'STATUS',
            dataIndex: 'status',
            key: 'status',
            width: 120,
            render: (status: string) => {
                const colorMap: { [key: string]: string } = { inactive: 'volcano', reserved: 'gold', maintenance: 'purple', active: 'geekblue' };
                return <Tag color={colorMap[status] || 'default'}>{status.toUpperCase()}</Tag>;
            }
        },
        {
            title: 'UPDATED BY',
            dataIndex: ['updater', 'name'],
            key: 'updated_by',
            width: 150,
            render: (name) => name || 'N/A'
        },
        {
            title: 'UPDATED AT',
            dataIndex: 'updated_at',
            key: 'updated_at',
            width: 180,
            render: (date: string) => new Date(date).toLocaleString('th-TH', {
                year: 'numeric', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit'
            })
        },
        {
            title: 'ACTION',
            key: 'action',
            width: 100,
            render: (_, record) => (
                <Space>
                    <Button type="link" icon={<EditOutlined />} onClick={() => handleEdit(record)} />
                </Space>
            ),
        },
    ];

    return (
        <>
            <Title level={2}>Bangrak IP Management</Title>
            <Text type="secondary">Overview of your Bangrak IPs.</Text>

            <Row gutter={[16, 16]} style={{ marginTop: 24 }}>
                <Col xs={12} sm={12} md={6}><Card><Statistic title="Total IPs" value={dbTotal} /></Card></Col>
                <Col xs={12} sm={12} md={6}><Card><Statistic title="Active" value={summary.active} valueStyle={{ color: '#3f8600' }} prefix={<ArrowUpOutlined />} /></Card></Col>
                <Col xs={12} sm={12} md={6}><Card><Statistic title="Inactive" value={summary.inactive} valueStyle={{ color: '#cf1322' }} prefix={<ArrowDownOutlined />} /></Card></Col>
                <Col xs={12} sm={12} md={6}><Card><Statistic title="Reserved" value={summary.reserved} /></Card></Col>
                <Col xs={12} sm={12} md={6}><Card><Statistic title="Maintenance" value={summary.maintenance} /></Card></Col>
            </Row>

            <Card
                title="Bangrak IP Address Table"
                style={{ marginTop: 24 }}
                extra={
                    <Space wrap>
                        <Search
                            placeholder="Search..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            onSearch={(value) => setSearchTerm(value)}
                            style={{ width: 200 }}
                        />
                        <Button onClick={handleExportExcel} loading={isExporting}>
                            Export Excel
                        </Button>
                        <Button type="primary" icon={<PlusOutlined />} onClick={handleAdd}>
                            Add New IP
                        </Button>
                    </Space>
                }
            >
                <Table
                    columns={columns}
                    dataSource={data}
                    rowKey="id"
                    loading={loading}
                    pagination={{
                        ...pagination,
                        showSizeChanger: true,
                        pageSizeOptions: ['5', '15', '20', '50', '100'],
                        showTotal: (total, range) => `${range[0]}-${range[1]} of ${total} items`,
                    }}
                    onChange={handleTableChange}
                    scroll={{ x: 1500 }}
                />
            </Card>

            <IpFormModal
                visible={isModalVisible}
                loading={isSubmitting}
                initialData={editingRecord}
                onCancel={handleCancel}
                onFinish={handleFormFinish}
            />
        </>
    );
};

export default IpBrkPage;