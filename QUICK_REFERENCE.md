# Quick Reference: Password Recovery & Office Bearer Approval

## 🔐 Password Recovery

### User Flow
```
/login.php → Click "Forgot your password?" 
  ↓
/forgot_password.php → Enter email 
  ↓
System generates token & shows recovery link 
  ↓
/reset_password.php?token=... → Enter new password 
  ↓
Login with new password ✅
```

### How It Works (Technical)
- Token: 256-bit random (bin2hex format)
- Storage: Encrypted in `reset_token` column
- Expiration: 15 minutes (checked via `token_expires_at`)
- Hashing: bcrypt (PASSWORD_DEFAULT)
- Single-use: Token cleared after reset

### Files
- Root: `/forgot_password.php`, `/reset_password.php` (shims)
- Logic: `/modules/auth/forgot_password_view.php`, `/modules/auth/reset_password_view.php`
- Guide: [/IMPLEMENTATION_GUIDE.md](IMPLEMENTATION_GUIDE.md)

---

## ✅ Office Bearer Approval

### Application Flow
```
User signs up as "Office Bearer" (category = partner)
  ↓
is_approved set to 0 (PENDING)
  ↓
User login redirects to "Pending Approval" page
  ↓
Cannot access dashboard until approved
  ↓
Super Admin reviews at /super_admin_dashboard.php
  ↓
Super Admin clicks "Approve" → is_approved = 1
  ↓
User receives notification & can login normally ✅
```

### Super Admin Controls
Go to: `/super_admin_dashboard.php`

**Pending Office Bearer Approvals Section (NEW)**
- Yellow warning card at top
- Shows count of pending applicants
- Lists: Name, Email, WhatsApp, Company, Application Date

**Actions:**
- **APPROVE** (Green) → Activates partner, sends congratulations message
- **REJECT** (Red) → Deletes registration, sends rejection message

**Users Table (Updated)**
- New "Status" column
- Partners show: "Approved" (green) or "Pending" (yellow)
- Others show: "N/A" (gray)

### Files Modified
- `/modules/auth/signup.php` - Sets is_approved = 0 for partners
- `/modules/auth/login.php` - Checks approval before login
- `/modules/portal/super_admin_dashboard.php` - Approval controls
- `/modules/portal/member_dashboard.php` - Shows pending page

---

## 🔧 Database Schema

### New Columns
```sql
ALTER TABLE users ADD COLUMN reset_token VARCHAR(64) NULL;
ALTER TABLE users ADD COLUMN token_expires_at DATETIME NULL;
ALTER TABLE users ADD COLUMN is_approved TINYINT(1) NOT NULL DEFAULT 1;
```

### Defaults
- Students: `is_approved = 1` (immediately active)
- Associates: `is_approved = 1` (immediately active)
- Partners: `is_approved = 0` (pending approval)

### Indexes
```sql
CREATE INDEX idx_users_reset_token ON users (reset_token);
CREATE INDEX idx_users_is_approved ON users (is_approved, category);
```

---

## ✅ Testing Checklist

- [ ] **Forgot Password**: Navigate to `/forgot_password.php`, test recovery link
- [ ] **Reset Password**: Use recovery link to set new password
- [ ] **Login with New Password**: Confirm new credentials work
- [ ] **Partner Signup**: Create account as "Office Bearer"
- [ ] **Pending Page**: Confirm pending page shows instead of dashboard
- [ ] **Admin Approval**: Approve partner in Super Admin dashboard
- [ ] **Partner Access**: Partner can now access full dashboard
- [ ] **Rejection Flow**: Test reject action (with confirmation)
- [ ] **Existing Users**: Confirm all existing logins still work

---

## 🚨 Important Notes

**No Breaking Changes:**
- All existing users unaffected (is_approved defaults to 1)
- Students & Associates immediately active
- Existing admin functions preserved
- Login flow unchanged for non-partners

**Security Features:**
- 256-bit cryptographic tokens
- 15-minute expiration enforced
- Single-use tokens (reuse prevented)
- Bcrypt password hashing
- XSS & CSRF protection

**Styling:**
- Forest Green (#102a54) & Gold (#d4af37) theme
- Responsive mobile design
- Crisp white cards with gradients
- All code unminified with detailed comments

---

## 📞 Quick Links

- [Full Implementation Guide](IMPLEMENTATION_GUIDE.md)
- [Password Reset Schema](schema_password_reset.sql)
- [Partner Approval Schema](schema_partner_approval.sql)
- [Forgot Password Logic](/modules/auth/forgot_password_view.php)
- [Reset Password Logic](/modules/auth/reset_password_view.php)
- [Super Admin Dashboard](/modules/portal/super_admin_dashboard.php)

