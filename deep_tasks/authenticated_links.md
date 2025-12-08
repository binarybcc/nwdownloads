# Authenticated Deep Link URLs

These URLs assume the user is **already logged in** (has an active session). They rely on the `task` and `action` parameters to route the user to the correct module without re-submitting credentials.

**Base URL:**
`https://seneca.newzware.com/ss70v2/seneca/common/login.jsp`

**Common Parameters:**
*   `site`: `seneca`
*   `module`: `sub`

---

## 1. Billing / Make Payment
```
https://seneca.newzware.com/ss70v2/seneca/common/login.jsp?site=seneca&module=sub&task=billing&action=billing_view
```

## 2. Vacation Maintenance
```
https://seneca.newzware.com/ss70v2/seneca/common/login.jsp?site=seneca&module=sub&task=vacation&action=vacation_view
```

## 3. Missed Delivery
```
https://seneca.newzware.com/ss70v2/seneca/common/login.jsp?site=seneca&module=sub&task=missed&action=missed_view
```

## 4. Account Profile
```
https://seneca.newzware.com/ss70v2/seneca/common/login.jsp?site=seneca&module=sub&task=account&action=account_view
```

## 5. Moves (Address Change)
```
https://seneca.newzware.com/ss70v2/seneca/common/login.jsp?site=seneca&module=sub&task=move&action=move_view
```

## 6. Complaints
```
https://seneca.newzware.com/ss70v2/seneca/common/login.jsp?site=seneca&module=sub&task=complaint&action=complaint_view
```

---

### Implementation Note
If `login.jsp` redirects users to the homepage when no credentials are provided (even with an active session), try replacing `login.jsp` with `template.jsp` in the URLs above:
`.../common/template.jsp?site=seneca&module=sub&task=billing&action=billing_view`
