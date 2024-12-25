# WordPress Autoscaling and Deployment Project

This document provides detailed steps for setting up a WordPress application with autoscaling, database configuration, GitHub workflow integration, and an EFS filesystem. Each step outlines how to accomplish the tasks specified in the project requirements.

---

## 1. Fork the WordPress Repository
1. Visit the [WordPress GitHub repository](https://github.com/WordPress/WordPress).
2. Click on the "Fork" button at the top-right corner of the page.
3. This will create a forked repository in your GitHub account.

---

## 2. Create an Autoscaling Group
### **Step 1: Launch Template**
1. Navigate to the EC2 dashboard in the AWS Management Console.
2. Go to **Launch Templates** and create a new launch template:
   - **AMI**: Amazon Linux 2 or Ubuntu (your choice).
   - **Instance Type**: `t3.small`.
   - **EBS Volume**: Allocate sufficient storage for the application.
   - **IAM Role**: Ensure the instance role has permissions for S3 and EFS.
   - Add the following to the user data script to mount the EFS filesystem:
     ```bash
     #!/bin/bash
     yum install -y amazon-efs-utils nfs-utils
     mkdir -p /wp-content/uploads
     echo "fs-0565b6d3d93dc002d:/ /wp-content/uploads efs defaults,_netdev 0 0" >> /etc/fstab
     mount -a
     ```

### **Step 2: Autoscaling Group**
1. Create an autoscaling group using the launch template:
   - **Min Instances**: 1
   - **Max Instances**: 2
   - **Subnets**: Public subnets in your new VPC.
   - Attach a target group linked to an Application Load Balancer (ALB).

---

## 3. GitHub Workflow
Create a GitHub Actions workflow file `.github/workflows/deploy.yml` with the following steps:

### **Step 1: Package the Repository**
```yaml
- name: Create tar file
  run: |
    mkdir /home/runner/work/build
    cp -r . /home/runner/work/build
    cd /home/runner/work/build
    tar -czvf app_$(date +"%Y%m%d%H%M").tar.gz *
```

### **Step 2: Push to S3**
```yaml
- name: Upload to S3
  run: |
    aws s3 cp app_$(date +"%Y%m%d%H%M").tar.gz s3://yourname_app/
```

### **Step 3: Deploy to Autoscaling Group Instances**
```yaml
- name: Deploy to instances
  run: |
    INSTANCE_IDS=$(aws ec2 describe-instances --filters "Name=tag:Environment,Values=wordpress-scaling" --query "Reservations[*].Instances[*].InstanceId" --output text)
    for INSTANCE_ID in $INSTANCE_IDS; do
      aws ssm send-command \
        --instance-ids "$INSTANCE_ID" \
        --document-name "AWS-RunShellScript" \
        --comment "Deploy WordPress build" \
        --parameters '{"commands":["aws s3 cp s3://yourname_app/app_$(date +\"%Y%m%d%H%M\").tar.gz /tmp/", "tar -xzf /tmp/app_$(date +\"%Y%m%d%H%M\").tar.gz -C /var/www/html", "rsync -av --delete /tmp/wordpress/ /var/www/html/"]}'
    done
```

### **Step 4: S3 Expiry Policy**
1. Go to the S3 bucket settings.
2. Create a lifecycle rule:
   - **Prefix**: `app_`
   - **Expiration**: Delete objects after 7 days.

---

## 4. Database Configuration
### **Step 1: Launch Database Instance**
1. Launch an EC2 instance in a private subnet.
   - **Instance Type**: `t3.micro` or larger.
   - **Security Group**: Allow MySQL (port 3306) access only from the autoscaling group instances.

### **Step 2: Database Setup**
1. SSH into the instance and install MySQL:
   ```bash
   sudo apt update
   sudo apt install -y mysql-server
   ```
2. Configure MySQL:
   - Create a database called `wordpress_db`.
   - Create a user with read-only permissions:
     ```sql
     CREATE USER 'readonly_user'@'%' IDENTIFIED BY 'password';
     GRANT SELECT ON wordpress_db.* TO 'readonly_user'@'%';
     FLUSH PRIVILEGES;
     ```

---

## 5. Networking Setup
### **VPC Configuration**
1. Create a new VPC tagged with your name.
2. Add subnets:
   - **Public Subnet**: For app instances.
   - **Private Subnet**: For the database.
3. Add a NAT Gateway to allow private subnet access to the internet.
4. Configure route tables for the subnets.

### **Security Groups**
1. App Instances:
   - Allow HTTP/HTTPS access from the internet.
   - Allow MySQL access to the database.
2. Database:
   - Allow MySQL access only from app instances.

---

## 6. EFS Filesystem
1. Create an EFS filesystem.
2. Attach the EFS filesystem to the app instances by adding the following in the launch template's user data:
   ```bash
   yum install -y amazon-efs-utils nfs-utils
   mkdir -p /wp-content/uploads
   echo "fs-0565b6d3d93dc002d:/ /wp-content/uploads efs defaults,_netdev 0 0" >> /etc/fstab
   mount -a
   ```

---

## 7. Developer Database Access
### **Solution**
1. Configure a bastion host:
   - Launch an EC2 instance in the public subnet.
   - Restrict SSH access to the developer’s IP address.
2. Use SSH tunneling:
   ```bash
   ssh -i maged-awskeypair.pem -L 3306:10.0.2.174:3306 ec2-user@<bastion-public-ip>
   ```
3. Connect to the database using a local MySQL client:
   ```bash
   mysql -u readonly_user -p -h 127.0.0.1
   ```

---

## 8. IAM Role for GitHub Workflow
1. Create an IAM role with the following permissions:
   - S3: `PutObject`, `GetObject`, `DeleteObject`
   - EC2: `DescribeInstances`
   - SSM: `SendCommand`
   - EFS: `MountTarget`
2. Attach the role to the GitHub Actions runner using OIDC.

---

## Conclusion
This setup ensures a scalable, secure, and automated deployment pipeline for the WordPress application while maintaining secure database access and efficient storage with EFS.

