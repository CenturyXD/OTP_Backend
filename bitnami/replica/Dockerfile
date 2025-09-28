FROM bitnami/postgresql-repmgr:17

# กำหนด environment variables สำหรับ replica
ENV POSTGRESQL_POSTGRES_PASSWORD=postgres \
    POSTGRESQL_USERNAME=postgres \
    POSTGRESQL_PASSWORD=postgres \
    POSTGRESQL_DATABASE=ip_management \
    REPMGR_PRIMARY_ROLE=standby \
    REPMGR_NODE_ID=2 \
    REPMGR_NODE_NAME=postgres_replica-2 \
    REPMGR_NODE_NETWORK_NAME=postgres_replica \
    REPMGR_PARTNER_NODES=postgres_master \
    REPMGR_PRIMARY_HOST=postgres_master \
    REPMGR_PASSWORD=repmgr_password \
    REPMGR_PORT_NUMBER=5432 \
    POSTGRESQL_ENABLE_LDAP=no \
    REPMGR_USE_REPMGRD=yes \
    POSTGRESQL_ENABLE_TLS=no

# ใช้ root user เพื่อรันคำสั่ง
USER root

# สร้างโฟลเดอร์สำหรับข้อมูลและเปลี่ยนเจ้าของ
RUN mkdir -p /bitnami/postgresql && \
    chown -R 1001:1001 /bitnami/postgresql && \
    chown -R 1001:1001 /opt/bitnami/postgresql/conf && \
    chown -R 1001:1001 /opt/bitnami/repmgr/conf && \
    chown -R 1001:1001 /opt/bitnami/postgresql/tmp

# สร้าง group และ user custom_user
RUN echo "custom_user:x:1001:" >> /etc/group && \
    echo "custom_user:x:1001:1001:Custom User:/home:/bin/bash" >> /etc/passwd && \
    mkdir -p /home/custom_user && \
    chown custom_user:custom_user /home/custom_user

# กลับไปใช้ user 1001
USER 1001