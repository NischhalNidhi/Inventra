# Nginx Equivalent Configuration

```nginx
location /uploads/ {
    # Disable script execution
    location ~ \.php$ {
        deny all;
    }
    
    # Force download of potential executable files if somehow bypassed
    types {
        application/octet-stream php php3 php4 php5 phtml;
    }
}
```
