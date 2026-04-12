# Nightly Maintenance Crons

## Login Attempts Cleanup
Run the following query nightly to clean up old login attempts:
```cron
0 2 * * * DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
```
