import React from 'react';
import { Button, Result, theme } from 'antd';
import { Link } from 'react-router-dom';

const AccessDeniedPage: React.FC = () => {
  const { token } = theme.useToken();

  // 1. สร้าง object style สำหรับ container หลักของหน้า
  const pageStyle: React.CSSProperties = {
    // 2. ใช้สีพื้นหลังสำหรับ container จาก theme (token.colorBgContainer)
    backgroundColor: token.colorBgContainer,
    // ตั้งค่าให้ div นี้เต็มความสูงของหน้าจอและจัดเนื้อหาอยู่ตรงกลาง
    minHeight: '100vh',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
  };

  return (
    // 3. สร้าง div ครอบ Result ทั้งหมด และใช้ style ที่เราสร้างขึ้น
    <div style={pageStyle}>
      <Result
        status="403"
        title={<span style={{ color: token.colorTextHeading }}>403</span>}
        subTitle={<span style={{ color: token.colorTextSecondary }}>Sorry, you are not authorized to access this page.</span>}
        extra={
          <Button type="primary">
            <Link to="/dashboard">Back Home</Link>
          </Button>
        }
      />
    </div>
  );
};

export default AccessDeniedPage;