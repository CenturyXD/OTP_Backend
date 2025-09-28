import React from 'react';
import { Button, Result, theme } from 'antd';
import { Link } from 'react-router-dom';

// 1. คอมเมนต์การ import รูปภาพออกไปก่อน
// import NotFoundIllustration from '../assets/not-found-illustration.svg';

const NotFoundPage: React.FC = () => {
  const { token } = theme.useToken();

  const pageStyle: React.CSSProperties = {
    backgroundColor: token.colorBgContainer,
    minHeight: '100vh',
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
  };


  return (
    <div style={pageStyle}>
      <Result
        status="404"
        title={<span style={{ color: token.colorTextHeading }}>404</span>}
        subTitle={<span style={{ color: token.colorTextSecondary }}>Sorry, the page you visited does not exist.</span>}
        extra={
          <Button type="primary">
            <Link to="/dashboard">Back Home</Link>
          </Button>
        }
      />
    </div>
  );
};

export default NotFoundPage;