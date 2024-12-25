# 🌐📈 WordPress Autoscaling and Deployment Project

📄 This document delineates the comprehensive steps necessary for the deployment and autoscaling of a WordPress application, including 📊 database configuration, 🌐 GitHub workflow integration, and 🗂️ EFS file system setup. Each section provides 🛠️ detailed instructions to fulfill the outlined requirements.

---

## Fork the WordPress Repository
1. Access the [WordPress GitHub repository](https://github.com/WordPress/WordPress).
2. Select the "Fork" button to replicate the repository into your GitHub account.

---

## Configure Autoscaling Group
### Launch Template Creation
1. In the EC2 dashboard, navigate to **Launch Templates** and initiate a new template:
   - **AMI**: Choose 🐧 Amazon Linux 2 or Ubuntu.
   - **Instance Type**: `t3.small`.
   - **Storage**: Allocate adequate EBS volume space.
   - **IAM Role**: Assign permissions for ☁️ S3 and EFS operations.
   - Add the following 🖥️ user data script:
     ```bash
     #!/bin/bash
     yum install -y amazon-efs-utils nfs-utils
     mkdir -p /wp-content/uploads
     echo "fs-0565b6d3d93dc002d:/ /wp-content/uploads efs defaults,_netdev 0 0" >> /etc/fstab
     mount -a
     ```

### Autoscaling Group Setup
1. Create an autoscaling group:
   - **Minimum Instances**: 1
   - **Maximum Instances**: 2
   - **Subnets**: Public subnets within your newly created 🏘️ VPC.
   - Integrate with an Application Load Balancer.

---

## GitHub Workflow
Create a `.github/workflows/deploy.yml` file incorporating the following steps:

### 📦 Package Repository
```yaml
- name: 📦 Package Repository
  run: |
    mkdir /home/runner/work/build
    cp -r . /home/runner/work/build
    cd /home/runner/work/build
    tar -czvf app_$(date +"%Y%m%d%H%M").tar.gz *
```

### ☁️ Upload to S3
```yaml
- name: ☁️ Upload to S3
  run: |
    aws s3 cp app_$(date +"%Y%m%d%H%M").tar.gz s3://yourname_app/
```

### 🚀 Deploy to Autoscaling Group
```yaml
- name: 🚀 Deploy Application
  run: |
    INSTANCE_IDS=$(aws ec2 describe-instances --filters "Name=tag:Environment,Values=wordpress-scaling" --query "Reservations[*].Instances[*].InstanceId" --output text)
    for INSTANCE_ID in $INSTANCE_IDS; do
      aws ssm send-command \
        --instance-ids "$INSTANCE_ID" \
        --document-name "AWS-RunShellScript" \
        --parameters '{"commands":["aws s3 cp s3://yourname_app/app_$(date +\\"%Y%m%d%H%M\\").tar.gz /tmp/", "tar -xzf /tmp/app_$(date +\\"%Y%m%d%H%M\\").tar.gz -C /var/www/html", "rsync -av --delete /tmp/wordpress/ /var/www/html/"]}'
    done
```

### 📜 S3 Expiry Policy
1. Navigate to the ☁️ S3 bucket.
2. Implement a 🕒 lifecycle policy:
   - **Prefix**: `app_`
   - **Expiration**: 7 days.

---

## Database Deployment
### 🖥️ Instance Setup
1. Launch an EC2 instance within a private subnet:
   - **Instance Type**: `t3.micro` or larger.
   - **Security Group**: Permit 🛡️ MySQL traffic (port 3306) exclusively from app instances.

### ⚙️ Database Configuration
1. Install MySQL:
   ```bash
   sudo apt update
   sudo apt install -y mysql-server
   ```
2. Establish a `wordpress_db` database and a read-only user:
   ```sql
   CREATE USER 'readonly_user'@'%' IDENTIFIED BY 'password';
   GRANT SELECT ON wordpress_db.* TO 'readonly_user'@'%';
   FLUSH PRIVILEGES;
   ```

---

## Networking Configuration
### 🌐 VPC Setup
1. Establish a VPC tagged with your name.
2. Configure subnets:
   - **Public**: App instances.
   - **Private**: Database instance.
3. Add a NAT Gateway for 🌍 internet access from the private subnet.
4. Set up appropriate 🗺️ route tables.

### 🛡️ Security Groups
1. **App Instances**:
   - Allow HTTP/HTTPS traffic.
   - Permit MySQL access to the database.
2. **Database**:
   - Restrict MySQL access to app instances only.

---

## EFS Filesystem Integration
1. Create an 🗂️ EFS filesystem.
2. Include the following script in the launch template's user data to ensure automatic mounting:
   ```bash
   yum install -y amazon-efs-utils nfs-utils
   mkdir -p /wp-content/uploads
   echo "fs-0565b6d3d93dc002d:/ /wp-content/uploads efs defaults,_netdev 0 0" >> /etc/fstab
   mount -a
   ```

---

## Secure Database Access
### Solution
1. Configure a 🛡️ bastion host:
   - Deploy an EC2 instance within the public subnet.
   - Restrict SSH access to the developer’s IP.
2. Use SSH tunneling:
   ```bash
   ssh -i maged-awskeypair.pem -L 3306:10.0.2.174:3306 ec2-user@<bastion-public-ip>
   ```
3. Access the database locally:
   ```bash
   mysql -u readonly_user -p -h 127.0.0.1
   ```

---

## IAM Role for GitHub Workflow
1. Create an 🛡️ IAM role with the following policies:
   - **S3**: `PutObject`, `GetObject`, `DeleteObject`.
   - **EC2**: `DescribeInstances`.
   - **SSM**: `SendCommand`.
   - **EFS**: `MountTarget`.
2. Configure OIDC to grant access to the GitHub Actions runner.

---

## 🔚 Conclusion
This project ensures a robust, scalable, and secure deployment pipeline for 🌐 WordPress, incorporating efficient 🗂️ storage solutions and 📊 database access mechanisms.

