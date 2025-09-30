import React, { useEffect } from 'react';
import { Modal, Form, Input, Select, Button } from 'antd';
import type { CoreIpData } from '../api/ApiClient';

const { Option } = Select;
const { TextArea } = Input;

// สร้าง Type สำหรับข้อมูลที่จะใช้สร้าง/แก้ไข
export type IpFormData = Omit<CoreIpData, 'id' | 'created_at' | 'updated_at'>;

interface IpFormModalProps {
    visible: boolean;
    loading: boolean;
    initialData: CoreIpData | null; // รับข้อมูลเริ่มต้น (ถ้ามีคือโหมด Edit)
    onCancel: () => void;
    onFinish: (values: IpFormData) => void;
    onDelete?: () => void; // ฟังก์ชันสำหรับลบ (optional)
}

const IpFormModal: React.FC<IpFormModalProps> = ({ visible, loading, initialData, onCancel, onFinish, onDelete }) => {
    const [form] = Form.useForm();
    const isEditMode = !!initialData; // ตรวจสอบว่าเป็นโหมด Edit หรือไม่

    // เมื่อ initialData เปลี่ยน (เช่น ผู้ใช้กด Edit รายการใหม่) ให้ตั้งค่าฟอร์ม
    useEffect(() => {
        if (initialData) {
            form.setFieldsValue(initialData);
        } else {
            form.resetFields();
        }
    }, [initialData, form]);

    const handleOk = () => {
        form.validateFields()
            .then(values => {
                onFinish(values as IpFormData);
            })
            .catch(info => {
                console.log('Validate Failed:', info);
            });
    };

    // สร้าง Footer ของ Modal เองเพื่อให้มีปุ่ม Delete
    const modalFooter = [
        <Button key="cancel" onClick={onCancel}>
            Cancel
        </Button>,
        isEditMode && onDelete && ( // แสดงปุ่ม Delete เฉพาะโหมด Edit
            <Button key="delete" type="primary" danger onClick={onDelete}>
                Delete
            </Button>
        ),
        <Button key="submit" type="primary" loading={loading} onClick={handleOk}>
            {isEditMode ? 'Save' : 'Add'}
        </Button>,
    ];

    return (
        <Modal
            title={isEditMode ? "Edit Data" : "Add Data"}
            open={visible}
            onCancel={onCancel}
            footer={modalFooter} // ใช้ Footer ที่สร้างขึ้นเอง
            destroyOnClose
            afterClose={() => form.resetFields()}
        >
            <Form
                form={form}
                layout="vertical"
                name="ip_form"
                initialValues={{ status: 'active' }}
            >
                <Form.Item name="ip_address" label="IP Address" rules={[{ required: true, message: 'Please input the IP Address!' }]}>
                    <Input />
                </Form.Item>
                <Form.Item name="division" label="Service" rules={[{ required: true, message: 'Please input the Service!' }]}>
                    <Input />
                </Form.Item>
                <Form.Item name="status" label="Status" rules={[{ required: true, message: 'Please select a status!' }]}>
                    <Select>
                        <Option value="active">Active</Option>
                        <Option value="inactive">Inactive</Option>
                        <Option value="reserved">Reserved</Option>
                        <Option value="maintenance">Maintenance</Option>
                    </Select>
                </Form.Item>
                <Form.Item name="contact" label="Contact" rules={[{ required: true, message: 'Please input the Contact!' }]}>
                    <Input />
                </Form.Item>
                <Form.Item name="phone" label="Phone" rules={[{ required: true, message: 'Please input the Phone number!' }]}>
                    <Input />
                </Form.Item>
                <Form.Item name="remark" label="Remark">
                    <TextArea rows={4} />
                </Form.Item>
            </Form>
        </Modal>
    );
};

export default IpFormModal;