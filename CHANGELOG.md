# Changelog

All notable changes to this project will be documented in this file. See [standard-version](https://github.com/conventional-changelog/standard-version) for commit guidelines.

## [2.2.0](https://github.com/binarybcc/nwdownloads/compare/v2.1.1...v2.2.0) (2026-08-21)

### Features

- **01-01:** add business unit trend functions and embed in overview response ([b0c5ba4](https://github.com/binarybcc/nwdownloads/commit/b0c5ba48e3ba9b28e32e8e30393970e37088266d))
- **01-01:** wire frontend to store business unit trend data ([8a3de0e](https://github.com/binarybcc/nwdownloads/commit/8a3de0e49eac80b424cf97768b67bcb560b04ca0))
- **02-01:** add BU trend chart helper function and lifecycle management ([5c7b0fb](https://github.com/binarybcc/nwdownloads/commit/5c7b0fb0564a58a0d051499d4eb557af0265d663))
- **02-01:** integrate trend chart into BU card template and rendering loop ([56ad874](https://github.com/binarybcc/nwdownloads/commit/56ad874fe1ac56ddd5c398b840a880ee068e227b))
- **04-01:** create fetch_call_logs.php CLI runner and gitignore credentials ([43b4975](https://github.com/binarybcc/nwdownloads/commit/43b49753acb2af73629a6366f91eca62d516a665))
- **04-01:** create MyCommPilotScraper class for BroadWorks call log scraping ([730314a](https://github.com/binarybcc/nwdownloads/commit/730314ac5664f56e497838f25b1d4c99102d31a7))
- **04-02:** add launchd plist for hourly call log scraping ([789b9f0](https://github.com/binarybcc/nwdownloads/commit/789b9f0e503356cce402b9b2a7cb3ceee02f00d3))
- **04:** replace macOS launchd with NAS-native daemon for call scraping ([82d327f](https://github.com/binarybcc/nwdownloads/commit/82d327f527e0a2231d7ab0c1bd2b1668fdf385c7))
- **05-01:** extend PHP backend from 4-week to 8-week expiration buckets ([5c96038](https://github.com/binarybcc/nwdownloads/commit/5c96038a537682800c1e699db39595f236d64ee3))
- **05-01:** update JS color gradient and chart title for 8-bar expiration view ([f6eb5ae](https://github.com/binarybcc/nwdownloads/commit/f6eb5ae8e9fba8857a0106fa3225dbb854946808))
- **06-01:** add call status data to expiration subscriber API ([11c71b5](https://github.com/binarybcc/nwdownloads/commit/11c71b58f52e0f04769a50a2e59d83552f10ee46))
- **06-02:** add call status column, sort toggle, tooltip, and sync timestamp to subscriber table ([ccd00a7](https://github.com/binarybcc/nwdownloads/commit/ccd00a73a7d85e09fd2d6fa381dd5c5d5316e35e))
- **06-02:** add status-based row fills, merged timestamp row, and 3 new columns to XLSX export ([43ff1ac](https://github.com/binarybcc/nwdownloads/commit/43ff1ac54e67a9f2667b059aa193126ec85a0618))
- **07-01:** add is_monthly SQL flag to all 8 bucket queries ([5872f04](https://github.com/binarybcc/nwdownloads/commit/5872f048fa6c2130f144d7d93438dbff0164ea93))
- **07-02:** add monthly-aware rendering, icons, and sorting to subscriber table ([ecb1b3c](https://github.com/binarybcc/nwdownloads/commit/ecb1b3cf59032f14d645bb1acd13cfafa98ccfa6))
- **07-02:** add monthly-aware XLSX export fill logic ([e4f985a](https://github.com/binarybcc/nwdownloads/commit/e4f985ad7d6a23c4b7a01934a6e810deab20f6e8))
- **08-01:** expand backend trend defaults to 13 weeks ([8242b55](https://github.com/binarybcc/nwdownloads/commit/8242b55e70980dc836c790bae567c19e15f9226e))
- **08-01:** update frontend labels and trend slider to 13-week defaults ([3ff3eaf](https://github.com/binarybcc/nwdownloads/commit/3ff3eaf29137c9df41147dd9906e2f20858965fb))
- **08-02:** add 90-day call log retention purge ([ac315bb](https://github.com/binarybcc/nwdownloads/commit/ac315bb62641f65f0c12390e4a5b8f174e6cccd3))
- **09-01:** add CSR report page and settings card ([a9aa50b](https://github.com/binarybcc/nwdownloads/commit/a9aa50bc4d25f7c413c107bbcdd6c0889c868c92))
- **09-01:** create CSR call report API endpoint ([f320175](https://github.com/binarybcc/nwdownloads/commit/f320175249ce58255082f673119b0217bd300d09))
- add new subscription starts import and chart integration ([#36](https://github.com/binarybcc/nwdownloads/issues/36)) ([0803c2f](https://github.com/binarybcc/nwdownloads/commit/0803c2fa92596f7a16cdd9cb31a6439b9dd6e081))
- **auth:** add Newzware cookie auto-login for cross-subdomain SSO ([6a5e0c0](https://github.com/binarybcc/nwdownloads/commit/6a5e0c05a4e758fbde1b5d59948d2f054c8d24e2))
- **automation:** add weekly auto-processor for Newzware inbox files ([4fc5c5d](https://github.com/binarybcc/nwdownloads/commit/4fc5c5da2ec102ecc99a2d2c7d1f8cab2c617b03))
- **automation:** switch daily import to NAS Task Scheduler ([17db889](https://github.com/binarybcc/nwdownloads/commit/17db889d3896285a966a7406673ddaeb1df77b3a))
- **chart:** add Subscribers by Zone trend chart to BU detail panel ([#41](https://github.com/binarybcc/nwdownloads/issues/41)) ([#41](https://github.com/binarybcc/nwdownloads/issues/41)) ([ee92585](https://github.com/binarybcc/nwdownloads/commit/ee925854e6ccdc3f0bdbf345ce229ac010d5d59b))
- **comp:** make paid subscribers the primary metric across dashboard ([#44](https://github.com/binarybcc/nwdownloads/issues/44)) ([205fb70](https://github.com/binarybcc/nwdownloads/commit/205fb70de0f184e388fdb307a49a754a7fc4bc4f))
- **comp:** track complimentary subscribers separately from paid ([#43](https://github.com/binarybcc/nwdownloads/issues/43)) ([471423c](https://github.com/binarybcc/nwdownloads/commit/471423c12a92ceefd71976c468982acf20a8cf4b))
- **csr:** add callback rate and calls-per-expiring-subscriber metrics ([259b7ac](https://github.com/binarybcc/nwdownloads/commit/259b7ac4b53443bcce966a7e28b010ae7b3e53f1))
- **csr:** add hero chart for calls to known subscribers with date range ([26a146f](https://github.com/binarybcc/nwdownloads/commit/26a146f819f6609d21ca324f5279b32da14be4ef))
- **csr:** filter missed calls to business hours only (M-F 8am-5pm ET) ([3acc91c](https://github.com/binarybcc/nwdownloads/commit/3acc91c08a10277e5d3914a16c3f1e2f18c0b727))
- **csr:** split call metrics by 4-digit extension vs external numbers ([7dc8e1a](https://github.com/binarybcc/nwdownloads/commit/7dc8e1aff57be65dc738cb2f644260e4c3c697a1))
- **dashboard:** add BU trend detail drill-down modal ([#32](https://github.com/binarybcc/nwdownloads/issues/32)) ([4f925e5](https://github.com/binarybcc/nwdownloads/commit/4f925e5762541197092b3456f8f9ba7200b5c2c6))
- **stops:** add stop analysis import, drill-down panel, and data source fix ([#39](https://github.com/binarybcc/nwdownloads/issues/39)) ([#39](https://github.com/binarybcc/nwdownloads/issues/39)) ([236a195](https://github.com/binarybcc/nwdownloads/commit/236a19550c3bb40f8604e1fa6ab052a31d69de0d))

### Bug Fixes

- **04:** move LOG_FILE constant above business-hours guard ([46edd01](https://github.com/binarybcc/nwdownloads/commit/46edd01c121bee1b3bfbd0e4e64d58e9a9167c01))
- **05:** update expiration chart heading from 4-Week to 8-Week View ([95e7648](https://github.com/binarybcc/nwdownloads/commit/95e7648f63f1ee0c20907454ce31157b97c32d08))
- **06:** collation mismatch, SheetJS styling, and cache-bust ([b6776aa](https://github.com/binarybcc/nwdownloads/commit/b6776aa247e862fc39e4b1945d5bb71b7464c95e))
- **06:** pass call_data_as_of from API to subscriber panel and export ([d2cb2d0](https://github.com/binarybcc/nwdownloads/commit/d2cb2d027f769171cc4c565bb8237f8fdc68320e))
- add is_monthly and call status to revenue_intelligence subscriber query ([18fd83f](https://github.com/binarybcc/nwdownloads/commit/18fd83f8519e34053a55ce67554a15b878ce740f))
- **api:** add missing $code parameter to legacy sendError function ([2350626](https://github.com/binarybcc/nwdownloads/commit/23506260b93af0a2b138c8c71d8e8c5c3359825f))
- **api:** eliminate N+1 query and harden BU trend detail endpoint ([#33](https://github.com/binarybcc/nwdownloads/issues/33)) ([d648d2f](https://github.com/binarybcc/nwdownloads/commit/d648d2fea900053455791b6e84dd44e81d02e9cc))
- **api:** suppress PHP error display in BU trend detail endpoint ([#34](https://github.com/binarybcc/nwdownloads/issues/34)) ([f3cadb7](https://github.com/binarybcc/nwdownloads/commit/f3cadb78d2ee6b7757125b24eb59e35352028885))
- **api:** use interpolated LIMIT in getZoneTrends for MariaDB compatibility ([#42](https://github.com/binarybcc/nwdownloads/issues/42)) ([baa3b97](https://github.com/binarybcc/nwdownloads/commit/baa3b97689bb7aa359e36f92d6b43cdedb730b44))
- **auth:** add CirculationDashboard namespace to NwAuth class (PSR-12) ([ed994d5](https://github.com/binarybcc/nwdownloads/commit/ed994d5199dd4da3fc9fa41c44085593a58b38d8))
- **auth:** handle IP addresses in getParentDomain() ([0efea0d](https://github.com/binarybcc/nwdownloads/commit/0efea0d357560e6f6886071f08701bf99a9422d9))
- **auth:** prefix global classes with backslash in namespaced NwAuth ([454644a](https://github.com/binarybcc/nwdownloads/commit/454644a9b586e5e6b43fb1e502a0ee1c1b00a285))
- **auth:** remove always-false ternary in NwAuth (PHPStan) ([64d49d1](https://github.com/binarybcc/nwdownloads/commit/64d49d1a5c83d60d3c61695dcf3e034316399197))
- **auth:** use parent domain for renewed hash cookie (SSO bug) ([d230187](https://github.com/binarybcc/nwdownloads/commit/d2301877a3870f9854c28a6911b8e06919b55a4e))
- **chart:** match print CSS heights to on-screen sizes for trend modal ([#38](https://github.com/binarybcc/nwdownloads/issues/38)) ([acf998c](https://github.com/binarybcc/nwdownloads/commit/acf998c6bbb9170bd1daac3979379e0e27fe801d))
- **import:** collapse duplicate subscriber rows in All Subscriber Report ([1e456c9](https://github.com/binarybcc/nwdownloads/commit/1e456c9e4a18ede7818bd2b64090446c4a77c289))
- **importer:** add YYYY-MM-DD date format support to NewStartsImporter ([#46](https://github.com/binarybcc/nwdownloads/issues/46)) ([653596e](https://github.com/binarybcc/nwdownloads/commit/653596e47d5a7704ca7883dfc98784bf07695b56))
- **import:** expire vacations instead of carrying them forever ([800d285](https://github.com/binarybcc/nwdownloads/commit/800d2853202ac4af627e8d3253c5693c2c8b4697)), closes [#53](https://github.com/binarybcc/nwdownloads/issues/53)
- **import:** preserve vacation state across weekly rebuilds ([54ba39d](https://github.com/binarybcc/nwdownloads/commit/54ba39d4d2f2496e2daed15c84211961ed9a152f))
- **ops:** catch Throwable and unify file-type routing ([c5e49b8](https://github.com/binarybcc/nwdownloads/commit/c5e49b8a704070535c414623c5f2d8c2781bf41a)), closes [#54](https://github.com/binarybcc/nwdownloads/issues/54)
- **ops:** wire failure alerts, fix CI, and clear dead code ([451fbb5](https://github.com/binarybcc/nwdownloads/commit/451fbb528ce20129c72756931367b3b1d8762502))
- **ui:** remove confusing percentage progress bars from BU cards ([#40](https://github.com/binarybcc/nwdownloads/issues/40)) ([#40](https://github.com/binarybcc/nwdownloads/issues/40)) ([9bc2148](https://github.com/binarybcc/nwdownloads/commit/9bc21488ad41b89634eb03299b769f120235ccf6))

### Documentation

- **01-01:** complete extend trend API plan ([2e52ea5](https://github.com/binarybcc/nwdownloads/commit/2e52ea5b351e6f71d5954d927f1d4662fbdcdbe6))
- **01:** capture phase context ([444594e](https://github.com/binarybcc/nwdownloads/commit/444594e44b342288524507c65d6a8e8fe3bbc337))
- **01:** complete business-unit-trend-data phase ([fd01b88](https://github.com/binarybcc/nwdownloads/commit/fd01b88307da99c9e1d51c32400f1ca486b11304))
- **01:** create phase plan ([48d20c9](https://github.com/binarybcc/nwdownloads/commit/48d20c94e26b11771f637c46a6b27357c333a6eb))
- **01:** research phase domain ([ad341e4](https://github.com/binarybcc/nwdownloads/commit/ad341e476f8e2ab7945a83c08893279230bf5034))
- **02-01:** complete chart rendering and card integration plan ([9610e20](https://github.com/binarybcc/nwdownloads/commit/9610e20ed2b82a9c14ee9bdbbc8cc8750180cb23))
- **02:** capture phase context ([45a55c3](https://github.com/binarybcc/nwdownloads/commit/45a55c3affa2a3841a63539f17c0ac67b70bbc69))
- **02:** complete chart-rendering-and-card-integration phase ([b65113a](https://github.com/binarybcc/nwdownloads/commit/b65113ac12d63bcebdffe2132beebde836235a73))
- **02:** create phase plan ([53e9cbb](https://github.com/binarybcc/nwdownloads/commit/53e9cbb888f8ae50075b6458495a5ec3b959a060))
- **02:** research phase domain ([4d5ed86](https://github.com/binarybcc/nwdownloads/commit/4d5ed8642be5d5b7e7366c5250f9aca69747e874))
- **03-01:** complete plan after checkpoint approval ([46cce92](https://github.com/binarybcc/nwdownloads/commit/46cce92bfaf342e3982faf641f767a342ade55e4)), closes [#46](https://github.com/binarybcc/nwdownloads/issues/46)
- **04-01:** complete call log scraper plan ([d15c091](https://github.com/binarybcc/nwdownloads/commit/d15c091e3a87d2a4b965933b54e3d7e4d10ccb31))
- **04-02:** complete launchd plist plan with verification results ([335ce57](https://github.com/binarybcc/nwdownloads/commit/335ce57221f5c5d86485454b97e45df35786d68b))
- **04-call-log-scraper:** create phase plan ([47c3e62](https://github.com/binarybcc/nwdownloads/commit/47c3e62fa695e2d9833bffc847bd4d473da2133b))
- **04:** capture phase context ([7c13a9c](https://github.com/binarybcc/nwdownloads/commit/7c13a9c634746761c402a0adebf3ac528fa1e3ce))
- **04:** research phase domain ([20a1a9f](https://github.com/binarybcc/nwdownloads/commit/20a1a9f4c5adb393fd7373aff32d8e30477c2d17))
- **05-01:** complete expiration chart expansion plan ([1762baa](https://github.com/binarybcc/nwdownloads/commit/1762baa24a2aadfa3f6754e3fa18103727e9933b))
- **05-expiration-chart-expansion:** research phase domain ([d7a1123](https://github.com/binarybcc/nwdownloads/commit/d7a11230ee9c75ac3a7e0cfd1e57b065b715a96a))
- **05:** add UI design contract for expiration chart expansion ([24c41f7](https://github.com/binarybcc/nwdownloads/commit/24c41f79d3c03f260155716e0c73a3e0c07745b4))
- **05:** create phase plan for expiration chart expansion ([5697f89](https://github.com/binarybcc/nwdownloads/commit/5697f89ed8ebdf689af1654fa968c659c2149d9a))
- **06-01:** complete call status API plan ([8db02d6](https://github.com/binarybcc/nwdownloads/commit/8db02d684b2f2f889549a591c3e005ebaaf79708))
- **06-02:** complete call status UI and export plan ([5cfc220](https://github.com/binarybcc/nwdownloads/commit/5cfc220419925beb8f8b2780c8a0543d73fc933c))
- **06:** add ui design contract ([4829bb9](https://github.com/binarybcc/nwdownloads/commit/4829bb954f5a02446caca3b093e88f342827409a))
- **06:** add UI design contract for call status UI and export ([068bc16](https://github.com/binarybcc/nwdownloads/commit/068bc1618bd70e3121fc2b87fbe26dfb102a6ad9))
- **06:** capture phase context ([d715286](https://github.com/binarybcc/nwdownloads/commit/d715286a2f6b11b75d9e64d62067a9e64f1def1f))
- **06:** create phase plan for call status UI and export ([0046207](https://github.com/binarybcc/nwdownloads/commit/004620762037627ad4d6454c3f49229ee170319b))
- **06:** research phase domain ([86e2799](https://github.com/binarybcc/nwdownloads/commit/86e279950b292e36fb27fe1f9109aa41904342cb))
- **07-01:** complete API is_monthly flag plan ([c138dff](https://github.com/binarybcc/nwdownloads/commit/c138dff2e4ac9977b6137c206866b76fc5ce4755))
- **07-02:** complete frontend monthly rendering plan ([2290b61](https://github.com/binarybcc/nwdownloads/commit/2290b61eec07c466e4dda36b0a5743fd798ad9ff))
- **07:** capture phase context ([c5e558b](https://github.com/binarybcc/nwdownloads/commit/c5e558bade2e56a0a4acabb6cbecf84b969b6161))
- **07:** create phase plan for monthly subscriber exemption ([8273cc2](https://github.com/binarybcc/nwdownloads/commit/8273cc245ef12343f8da361c61d5bd6f9d72572a))
- **07:** research phase domain ([4fe061b](https://github.com/binarybcc/nwdownloads/commit/4fe061b01d000d9a7af1b654259cce2f6380a890))
- **08-01:** complete trend expansion plan ([d2c2316](https://github.com/binarybcc/nwdownloads/commit/d2c2316b9f2ac34183ddd178d2e28875be79a632))
- **08-02:** complete call log retention purge plan ([f52848d](https://github.com/binarybcc/nwdownloads/commit/f52848d0969394a58a544c3af419678578ec406e))
- **08:** capture phase context ([67e07f1](https://github.com/binarybcc/nwdownloads/commit/67e07f1328f430bef3b52e735b9f5a7dad331294))
- **08:** create phase plan ([8f5e6a1](https://github.com/binarybcc/nwdownloads/commit/8f5e6a1c51ac3549d30f6a92bbd74e9a6ea7b40b))
- **08:** research phase domain ([ae6bf79](https://github.com/binarybcc/nwdownloads/commit/ae6bf79cc41d53d86cfbb5a22c7ebd7c7809989a))
- **09-01:** complete CSR call reporting plan ([c101b18](https://github.com/binarybcc/nwdownloads/commit/c101b188cb419ffb0fc86c61dc8207de56fe1d5e))
- **09:** capture phase context ([23c8533](https://github.com/binarybcc/nwdownloads/commit/23c853331499b9322c78883ff9cfc16e0f142b52))
- **09:** create phase plan for CSR call reporting ([8994eed](https://github.com/binarybcc/nwdownloads/commit/8994eed0abb3e1d078fd68409a8185361d0e399d))
- **09:** research CSR call reporting phase ([194da88](https://github.com/binarybcc/nwdownloads/commit/194da88a9b398a2471b8a994790016bd67ce2a4d))
- add prompt for daily import cron migration (Mon-Sat) ([68ad90b](https://github.com/binarybcc/nwdownloads/commit/68ad90b470c9118d67bb037ca16f7b83b9c8f9b6))
- create milestone v2.2 roadmap (3 phases) ([91f2fee](https://github.com/binarybcc/nwdownloads/commit/91f2fee75f2c6316cbc54ed93d9f4c0d5edfbee5))
- create roadmap (2 phases) ([36ad36f](https://github.com/binarybcc/nwdownloads/commit/36ad36fd3d411dd623b02a68e21f6e3979e58763))
- define milestone v2.2 requirements ([613ca31](https://github.com/binarybcc/nwdownloads/commit/613ca3188dc42cc712371f5981d9b5dc27bfc4ef))
- define v1 requirements ([8ab6077](https://github.com/binarybcc/nwdownloads/commit/8ab607753a3e2052dbf428e606cc746e6c68e163))
- initialize project ([bd2f750](https://github.com/binarybcc/nwdownloads/commit/bd2f750666559a753bd22a94851d9279b38050ef))
- map existing codebase ([bbc8085](https://github.com/binarybcc/nwdownloads/commit/bbc8085e669df3495209b5bd34dacd4cfc3d62d4))
- **phase-03:** complete phase execution and verification ([63244f4](https://github.com/binarybcc/nwdownloads/commit/63244f4512ae2bf8eaddad9c09b8f6d68d40c48a))
- **phase-04:** complete phase execution and verification ([898e7c3](https://github.com/binarybcc/nwdownloads/commit/898e7c3faefd912799b8bd00dc49090bcc84270c))
- **phase-05:** complete phase execution and verification ([499d443](https://github.com/binarybcc/nwdownloads/commit/499d4431381250306cd77e7c5cd682cbf751e9a2))
- **phase-05:** evolve PROJECT.md after phase completion ([09f5950](https://github.com/binarybcc/nwdownloads/commit/09f595084463088b7ffb4cc2dc2158dce7eb3ae0))
- **phase-06:** complete phase execution and verification ([41a6d70](https://github.com/binarybcc/nwdownloads/commit/41a6d70ebd250263064f4b7b3150a20dbb785df0))
- **phase-06:** evolve PROJECT.md after phase completion ([809e92b](https://github.com/binarybcc/nwdownloads/commit/809e92bf866c2aa9ce51fb15fa7a020b7726256c))
- **phase-07:** complete phase execution and verification ([89fbb38](https://github.com/binarybcc/nwdownloads/commit/89fbb38d577fdecea1de8d2fb0141da5fb12e0d0))
- **phase-08:** add validation strategy ([14c2947](https://github.com/binarybcc/nwdownloads/commit/14c29471dbf198f7291595513c1a6172e45b4852))
- **phase-08:** complete phase execution ([037d167](https://github.com/binarybcc/nwdownloads/commit/037d1676904d93ee100840f32aad43ed0599fe43))
- **phase-4:** add research and validation strategy ([3d93f4d](https://github.com/binarybcc/nwdownloads/commit/3d93f4d22ce2db20d604c4bbdd8121c57cc8ec4e))
- **phase-5:** add research and validation strategy ([a720d8c](https://github.com/binarybcc/nwdownloads/commit/a720d8c51a7ee35fcdc365a99f6f3d33ae6edc8f))
- start milestone v2.2 Monthly Subscriber Handling & Dashboard Refinements ([9415248](https://github.com/binarybcc/nwdownloads/commit/94152487b9f460cdf194fa644de1dd5ce3270125))
- **state:** record phase 4 context session ([1cf2a29](https://github.com/binarybcc/nwdownloads/commit/1cf2a29b405d96995a9fc19621b5d343bdb43df1))
- **state:** record phase 6 context session ([d605cff](https://github.com/binarybcc/nwdownloads/commit/d605cff69f48cef0b936036a4111c365716a4625))
- **state:** record phase 7 context session ([b881931](https://github.com/binarybcc/nwdownloads/commit/b8819310a98796c2ae1b00f4816c0ef5636e4c55))
- **state:** record phase 8 context session ([629ed6f](https://github.com/binarybcc/nwdownloads/commit/629ed6ff7fe9a7643c4bf2df482472bde130f16d))
- **state:** record phase 9 context session ([dda75d1](https://github.com/binarybcc/nwdownloads/commit/dda75d1babbc142b833a53c064698d53a4195a27))
- **v2.1:** milestone audit — 15/16 requirements satisfied ([48a8881](https://github.com/binarybcc/nwdownloads/commit/48a8881e2332fdd8db0c55fcdf73aa2d14dd31fe))
- **v2.1:** resolve CALL-03 — scraper runs as NAS daemon, audit passes ([a50e971](https://github.com/binarybcc/nwdownloads/commit/a50e971b5df077ed4de7a26848aed940a70249ab))

### Code Refactoring

- **chart:** rename Starts to Renewals and split into dual-panel layout ([#37](https://github.com/binarybcc/nwdownloads/issues/37)) ([c3da2e0](https://github.com/binarybcc/nwdownloads/commit/c3da2e0f7e1c6f072fc9e543bc8a699e682a320e))
- **import:** address review on duplicate-subscriber collapsing ([343c08d](https://github.com/binarybcc/nwdownloads/commit/343c08d101c4f685a0348a76769868284baad52c))

### Maintenance

- add project config ([61943ec](https://github.com/binarybcc/nwdownloads/commit/61943ecffa5ed6a4405b2dd2085be5d84be7fb2a))
- complete v1 milestone ([e026662](https://github.com/binarybcc/nwdownloads/commit/e026662995c4ce995da9dd48caf14ebc32a3c2b1))
- complete v2.1 milestone — Call Integration & Dashboard Enhancements ([9607ee0](https://github.com/binarybcc/nwdownloads/commit/9607ee06eb6c55b6a6be93b84fe35d73690cea78))
- compress CLAUDE.md and clean up project orphans ([#45](https://github.com/binarybcc/nwdownloads/issues/45)) ([ac6b84c](https://github.com/binarybcc/nwdownloads/commit/ac6b84ca03710e71d6e58370be34b5a9f050ca90))
- **deps:** pin composer to production's PHP 8.2 ([243df9a](https://github.com/binarybcc/nwdownloads/commit/243df9af6702c489db645a45148aabdaa1b209e5))
- remove all Docker references from project ([1695146](https://github.com/binarybcc/nwdownloads/commit/16951469927a1b6837aadc5207cad89c1b2e5243))

### [2.1.1](https://github.com/binarybcc/nwdownloads/compare/v2.1.0...v2.1.1) (2026-02-09)

### Bug Fixes

- **api:** correct Previous Week comparison in detail panel ([be2e3a3](https://github.com/binarybcc/nwdownloads/commit/be2e3a30e7c4b428682c2a0e62b566f7c6147470))
- **api:** correct year calculation for ISO week boundaries ([182de08](https://github.com/binarybcc/nwdownloads/commit/182de0860a03cdce52ff654458b2a8646a9a9a46))

## [2.1.0](https://github.com/binarybcc/nwdownloads/compare/v2.0.0...v2.1.0) (2025-12-24)

### Features

- **db:** Migration Safety System & Operational Scripts ([#31](https://github.com/binarybcc/nwdownloads/issues/31)) ([23c678a](https://github.com/binarybcc/nwdownloads/commit/23c678aa504aaad93d69d9da154c765c694ac1a7))
- **tooling:** add automated versioning with standard-version ([32c01d4](https://github.com/binarybcc/nwdownloads/commit/32c01d411ef6f88acf161d086332a014076eea3a))
- **upload:** consolidate renewals into unified upload interface ([e32aae5](https://github.com/binarybcc/nwdownloads/commit/e32aae5be7fa4978af45a848045c999f0c3c51ee))

### Bug Fixes

- **database:** remove column positioning from migration script ([335ac66](https://github.com/binarybcc/nwdownloads/commit/335ac660961deb2874a7a63d4c044586e8a74791))

### Documentation

- Add CHANGELOG.md and version engineering standards ([ce3d5e1](https://github.com/binarybcc/nwdownloads/commit/ce3d5e11a48955623f67cc9551042123c38e0c10))

### Maintenance

- add package.json for version tracking and tooling ([265e33c](https://github.com/binarybcc/nwdownloads/commit/265e33c611bf675c0d7063378ee9a549626bc8b8))
- add project configuration and documentation ([e355036](https://github.com/binarybcc/nwdownloads/commit/e355036ccd874ff31297b502e0a3ef9fc176d1d3))
- **hooks:** add pre-commit hooks with husky and commitlint ([2a6a027](https://github.com/binarybcc/nwdownloads/commit/2a6a027133832562aef7665324b29f026d2e4d23))

## [2.0.0] - 2025-12-23

### Changed

- **BREAKING**: Refactored upload code paths to eliminate divergent implementations (#30)
- Moved minimum backfill date to class constant for maintainability

### Fixed

- Upload interface regression and upload blocking logic
- SoftBackfill logic to respect real vs backfilled data
- File processing to use Unix socket for production database
- Minimum backfill date to allow Nov 24 uploads

### Added

- Automated file processing system (Phase 1, 2, 3)
- Automated backup and restore system (#27)
- Technical debt tracking documentation
- Detailed logging for upload troubleshooting

### Documentation

- Added CRITICAL tech debt documentation for divergent upload code paths
- Clarified SoftBackfill logic for real vs backfilled data
- Added backup system design with security measures

---

## [Unreleased]

### To Be Categorized

_The following untracked files need review:_

- Migration scripts in `database/migrations/`
- New utility scripts in `scripts/`
- Diagnostic tools in `web/`

---

## Version History (Pre-Changelog)

_Note: This changelog was initialized on 2025-12-23. Previous development
history can be found in git log. Future changes will be documented here
following conventional changelog format._
