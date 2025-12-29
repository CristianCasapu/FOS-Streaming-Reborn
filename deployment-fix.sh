#!/bin/bash
# Fix deployment by copying files to the correct nginx document root

echo "Fixing FOS-Streaming deployment..."

# Create the target directory structure
sudo mkdir -p /home/fosadmin/FOS-Streaming
sudo chown -R fosadmin:fosadmin /home/fosadmin/FOS-Streaming

# Copy your application files to the nginx document root
cp -r /home/casapu/projects/FOS-Streaming-v69/* /home/fosadmin/FOS-Streaming/

# Set proper ownership
sudo chown -R fosadmin:fosadmin /home/fosadmin/FOS-Streaming

# Create logs directory
mkdir -p /home/fosadmin/FOS-Streaming/logs
sudo chown -R fosadmin:fosadmin /home/fosadmin/FOS-Streaming/logs

# Check if PHP-FPM admin socket exists, if not create it
if [ ! -S /run/php/php8.4-fpm-admin.sock ]; then
    echo "Creating PHP-FPM admin pool configuration..."
    
    # Create PHP-FPM admin pool config
    sudo tee /etc/php/8.4/fpm/pool.d/fos-admin.conf > /dev/null <<'EOF'
[fos-admin]
user = fosadmin
group = fosadmin
listen = /run/php/php8.4-fpm-admin.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 20
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 500

; Environment variables
env[PATH] = /usr/local/bin:/usr/bin:/bin
env[TMP] = /tmp
env[TMPDIR] = /tmp
env[TEMP] = /tmp

; PHP settings
php_admin_value[error_log] = /var/log/php8.4-fpm-admin.log
php_admin_flag[log_errors] = on
php_value[session.save_handler] = files
php_value[session.save_path] = /var/lib/php/sessions
php_value[soap.wsdl_cache_dir] = /var/lib/php/wsdlcache
EOF

    # Restart PHP-FPM
    sudo systemctl restart php8.4-fpm
    
    echo "PHP-FPM admin pool created and restarted"
fi

# Set permissions
sudo chmod -R 755 /home/fosadmin/FOS-Streaming
sudo chmod -R 777 /home/fosadmin/FOS-Streaming/logs

# Test nginx configuration
sudo nginx -t

if [ $? -eq 0 ]; then
    sudo systemctl reload nginx
    echo "✅ Deployment fixed! Nginx reloaded."
    echo "Your application should now be accessible at http://152.53.95.152:8000"
else
    echo "❌ Nginx configuration test failed. Please check the error above."
fi