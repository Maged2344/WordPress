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
     
      sudo apt update -y
      sudo apt upgrade -y
      sudo add-apt-repository ppa:ondrej/php -y
      sudo apt update -y
      sudo apt install php8.0 php8.0-fpm php8.0-mysql php8.0-xml php8.0-mbstring php8.0-curl php8.0-zip php8.0-gd -y
      sudo systemctl start php8.0-fpm
      sudo systemctl enable php8.0-fpm
      sudo curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "awscliv2.zip"
      sudo apt-get install unzip -y
      sudo unzip awscliv2.zip
      sudo ./aws/install
      sudo apt update
      sudo apt upgrade -y
      sudo apt install nginx -y
      sudo bash -c 'cat > /etc/nginx/sites-available/default <<EOF
      server {
          listen 80 default_server;
          listen [::]:80 default_server;
          root /var/www/html/wordpress;
          index index.php index.html index.htm;
          server_name _;
          location / {
              try_files \$uri \$uri/ =404;
          }
          location ~ \.php$ {
              include snippets/fastcgi-php.conf;
              fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
              fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
              include fastcgi_params;
          }
          location ~ /\.ht {
              deny all;
          }
      }
      EOF'
      sudo systemctl restart nginx
      sudo apt install git -y
      
      sudo mkdir /var/www/html/s3-download
      cd /var/www/html/s3-download
      sudo aws s3 cp s3://maged-bucket/wordpress-s3/ . --recursive
      sudo mkdir /var/www/html/wordpress
      sudo tar -xzvf * -C /var/www/html/wordpress/
      
      sudo rm -rf *
      sudo chown -R www-data:www-data /var/www/html/wordpress
      sudo chmod -R 755 /var/www/html/wordpress
      sudo bash -c 'cat > /var/www/html/wordpress/wp-config.php <<EOF
      <?php
      define( "DB_NAME", "database-name" );
      define( "DB_USER", "db_user" );
      define( "DB_PASSWORD", "password" );
      define( "DB_HOST", "private-ip" );
      define( "DB_CHARSET", "utf8mb4" );
      define( "DB_COLLATE", "" );
      \$table_prefix  = "wp_";
      if ( !defined("ABSPATH") )
          define("ABSPATH", dirname(__FILE__) . "/");
      require_once(ABSPATH . "wp-settings.php");
      EOF'
      sudo systemctl restart nginx
      sudo nginx -t
      sudo systemctl restart nginx


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
        DATE=$(date +%Y-%m-%d_%H-%M)
        sudo mkdir /home/runner/work/maged
        sudo cp -r /home/runner/work/WordPress/WordPress/* /home/runner/work/maged
        cd /home/runner/work/maged
        sudo tar -czvf app_$DATE.tar.gz *
```

### ☁️ Upload to S3
```yaml
        DATE=$(date +%Y-%m-%d_%H-%M)
        S3_BUCKET="s3://maged-bucket" 
        aws s3 mv s3://maged-bucket/wordpress-s3/ s3://maged-bucket/wordpress-s3-old/ --recursive
        aws s3 cp /home/runner/work/maged/app_$DATE.tar.gz s3://maged-bucket/wordpress-s3/

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
   - **S3**: `PutObject`, `GetObject`, `DeleteObject`, `ListBucket`, `GetObjectTagging`.
   - **EC2**: `DescribeInstances`.
   - **EFS**: `MountTarget`.
2. Configure OIDC to grant access to the GitHub Actions runner.

---

## 🔚 Conclusion
This project ensures a robust, scalable, and secure deployment pipeline for 🌐 WordPress, incorporating efficient 🗂️ storage solutions and 📊 database access mechanisms.

