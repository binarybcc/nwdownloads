# Quick Start: Testing Contextual Chart Menus

**⏱️ 5-Minute Quick Test** | Last Updated: 2025-12-05

---

## 🚀 Fastest Way to Test

### Step 1: Open Dashboard
```
http://localhost:8081
```

### Step 2: Open Detail Panel
Click on **any business unit card** (Wyoming, Michigan, or South Carolina)

### Step 3: Right-Click on Chart Bar
Scroll to "Subscription Expirations (4-Week View)" chart
**Right-click** on the **"Past Due"** bar

### Step 4: Try Actions

**Option A: View Historical Trend**
1. Select "📈 Show trend over time"
2. Watch animation (bar slides left, line chart slides in)
3. Click "← Back" button to return

**Option B: View Subscribers**
1. Select "👥 View subscribers"
2. See teal panel slide in with subscriber table
3. Click "📗 Export to Excel" to download formatted file
4. Click X or press ESC to close

---

## ✅ Success Checklist

If you see these, everything is working:

- ✅ Context menu appears on right-click
- ✅ Smooth slide animations (no jank)
- ✅ Teal/cyan color scheme on subscriber panel (not blue)
- ✅ Excel downloads with teal header and alternating rows
- ✅ No JavaScript errors in browser console (F12)

---

## 🐛 Common Issues

**Context menu doesn't appear?**
- Check browser console for JavaScript errors
- Verify all 5 new scripts loaded in Network tab
- Try hard refresh (Cmd+Shift+R on Mac, Ctrl+Shift+F5 on Windows)

**"Failed to load subscriber data" error?**
- Check Docker containers are running: `docker compose ps`
- Verify API endpoint works: `curl http://localhost:8081/api.php?action=data_range`
- Check PHP error logs: `docker compose logs web`

**Excel export doesn't work?**
- Check if SheetJS library loaded (should be in page source)
- Try CSV export instead (simpler, no dependencies)

**Animations are choppy?**
- Close other browser tabs (reduce memory pressure)
- Check CPU usage (Docker might be resource-constrained)

---

## 📞 Next Steps

**If everything works:**
1. Review full testing guide: `docs/TESTING-CONTEXTUAL-MENUS.md`
2. Test all 10 scenarios
3. Approve for Production deployment

**If issues found:**
1. Note specific steps to reproduce
2. Check browser console for error messages
3. Report using bug template in testing guide

---

## 🚢 Deploy to Production

**When ready:**
```bash
# 1. Create archive of current production (backup)
cd /Users/johncorbin/Desktop/projs/nwdownloads
tar czf ../nwdownloads_backup_$(date +%Y%m%d).tar.gz web/

# 2. Copy to Synology NAS
sshpass -p 'Mojave48ice' scp -r web/* it@192.168.1.254:/volume1/docker/nwdownloads/web/

# 3. SSH into NAS and restart
sshpass -p 'Mojave48ice' ssh it@192.168.1.254 'cd /volume1/docker/nwdownloads && sudo /usr/local/bin/docker compose restart'

# 4. Test production
open http://192.168.1.254:8081/
```

---

**That's it! 🎉**

*For detailed info, see: `docs/IMPLEMENTATION-SUMMARY.md`*
