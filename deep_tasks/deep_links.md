# Deep Link URLs

Here are the complete URL patterns for the requested tasks.

**Base URL Pattern:**
`https://seneca.newzware.com/ss70v2/seneca/common/login.jsp`

**Common Parameters:**
*   `site`: `seneca`
*   `module`: `sub`
*   `login_id`: `[LOGIN_ID]` (Replace with actual username)
*   `password`: `[PASSWORD]` (Replace with actual password)

---

## 1. Billing / Make Payment
Directs the user to the payment screen.

```
https://seneca.newzware.com/ss70v2/seneca/common/login.jsp?site=seneca&login_id=[LOGIN_ID]&password=[PASSWORD]&module=sub&task=billing&action=billing_view
```

## 2. Vacation Maintenance
Directs the user to the vacation scheduling screen.

```
https://seneca.newzware.com/ss70v2/seneca/common/login.jsp?site=seneca&login_id=[LOGIN_ID]&password=[PASSWORD]&module=sub&task=vacation&action=vacation_view
```

## 3. Missed Delivery
Directs the user to the missed delivery reporting screen.

```
https://seneca.newzware.com/ss70v2/seneca/common/login.jsp?site=seneca&login_id=[LOGIN_ID]&password=[PASSWORD]&module=sub&task=missed&action=missed_view
```

---

### Usage Note
These URLs contain sensitive credentials (`password`). They should be generated dynamically on the server-side (like in our PHP script) and then the user redirected, rather than being exposed directly in client-side HTML links if possible.
