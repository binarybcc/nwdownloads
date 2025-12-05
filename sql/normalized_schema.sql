-- ============================================
-- Normalized Circulation Database Schema
-- Based on Newzware ERD
-- Version: 2.0.0
-- Created: 2025-12-03
-- ============================================
-- This schema normalizes the circulation data into proper relational tables
-- following the Newzware data model structure.
--
-- Migration Strategy:
-- 1. Create all normalized tables (this file)
-- 2. Keep existing subscriber_snapshots for historical imports
-- 3. Gradually migrate features to use normalized tables
-- 4. Eventually phase out snapshot table once all features migrated
-- ============================================

-- ============================================
-- CORE ENTITIES
-- ============================================

-- Addresses (normalized, one address can have multiple occupants)
CREATE TABLE IF NOT EXISTS addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Parsed address components
    primary_range VARCHAR(12) COMMENT 'Street number',
    pre_direction VARCHAR(3) COMMENT 'N, S, E, W, etc.',
    street_name VARCHAR(30),
    post_direction VARCHAR(3),
    suffix VARCHAR(5) COMMENT 'St, Ave, Rd, etc.',
    unit VARCHAR(5) COMMENT 'Apt, Suite, Unit number',
    secondary_range VARCHAR(8),

    -- City/State/Zip
    city VARCHAR(25),
    state VARCHAR(4),
    zip VARCHAR(9),
    zip4 VARCHAR(4),
    county VARCHAR(3),
    country VARCHAR(4) DEFAULT 'USA',

    -- USPS data
    carrier_route VARCHAR(5) COMMENT 'USPS carrier route',
    delivery_point_barcode VARCHAR(2),
    postal_zone VARCHAR(1),
    postal_sequence INT,

    -- Geocoding
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    geo_msa VARCHAR(4) COMMENT 'Metropolitan Statistical Area',
    geo_block FLOAT,
    geo_match VARCHAR(1) COMMENT 'Geocoding match quality',

    -- Address flags
    is_valid BOOLEAN DEFAULT TRUE COMMENT 'Address validated',
    is_vacant BOOLEAN DEFAULT FALSE,
    is_seasonal BOOLEAN DEFAULT FALSE,

    -- Delivery metadata
    route_type VARCHAR(1) COMMENT 'Route classification',
    drop_type VARCHAR(1) COMMENT 'Drop point type',
    delivery_type VARCHAR(1) COMMENT 'Mail, carrier, etc.',
    delivery_point_count INT DEFAULT 1,
    is_throwback BOOLEAN DEFAULT FALSE,
    record_type VARCHAR(1),

    -- Special address types
    po_box_number VARCHAR(11),
    rural_route_number VARCHAR(11),
    rural_route_box VARCHAR(11),
    firm_line VARCHAR(60) COMMENT 'Business name for firm drops',

    -- Validation tracking
    validation_error_code VARCHAR(4),
    validation_status_code VARCHAR(4),

    -- Internal tracking
    address_hash INT COMMENT 'Hash for duplicate detection',
    link_count INT DEFAULT 0 COMMENT 'Number of occupants at address',
    lot_number INT,
    lot_order VARCHAR(1),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_zip (zip),
    INDEX idx_city (city),
    INDEX idx_state (state),
    INDEX idx_carrier_route (carrier_route),
    INDEX idx_address_hash (address_hash),
    INDEX idx_geo (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Normalized addresses - one address, many occupants';


-- Subscribers/Occupants (people who receive papers)
CREATE TABLE IF NOT EXISTS subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Name components
    first_name VARCHAR(20),
    last_name VARCHAR(40),
    title VARCHAR(4) COMMENT 'Dr, Mr, Mrs, etc.',
    prefix VARCHAR(4),
    suffix VARCHAR(25) COMMENT 'Jr, Sr, III, etc.',
    care_of VARCHAR(50) COMMENT 'c/o name',

    -- Foreign keys
    address_id INT NOT NULL COMMENT 'Current address',
    distributor_id INT COMMENT 'Assigned distributor/carrier',
    advertiser_id INT COMMENT 'Related advertiser account',
    subscription_id INT COMMENT 'Primary active subscription',
    supplier_id INT COMMENT 'Supplier relationship',

    -- Account settings
    consolidated_billing BOOLEAN DEFAULT FALSE COMMENT 'Bill all subs together',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (address_id) REFERENCES addresses(id) ON DELETE RESTRICT,

    INDEX idx_last_name (last_name),
    INDEX idx_full_name (last_name, first_name),
    INDEX idx_address (address_id),
    INDEX idx_distributor (distributor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Subscribers (occupants) - people who receive newspapers';


-- Contact Information (phone numbers)
CREATE TABLE IF NOT EXISTS subscriber_phones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subscriber_id INT NOT NULL,

    phone_number VARCHAR(18),
    phone_type TINYINT COMMENT '1=home, 2=work, 3=mobile, etc.',
    is_primary BOOLEAN DEFAULT FALSE,
    do_not_call BOOLEAN DEFAULT FALSE COMMENT 'DNC flag',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE,

    INDEX idx_subscriber (subscriber_id),
    INDEX idx_phone (phone_number),
    INDEX idx_primary (subscriber_id, is_primary)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Subscriber phone numbers';


-- Contact Information (emails)
CREATE TABLE IF NOT EXISTS subscriber_emails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subscriber_id INT NOT NULL,

    email VARCHAR(100),
    email_type TINYINT COMMENT '1=personal, 2=work, etc.',
    is_primary BOOLEAN DEFAULT FALSE,
    do_not_email BOOLEAN DEFAULT FALSE COMMENT 'DNE flag',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE CASCADE,

    INDEX idx_subscriber (subscriber_id),
    INDEX idx_email (email),
    INDEX idx_primary (subscriber_id, is_primary)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Subscriber email addresses';


-- ============================================
-- SUBSCRIPTION MANAGEMENT
-- ============================================

-- Papers/Publications
CREATE TABLE IF NOT EXISTS publications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) UNIQUE NOT NULL COMMENT 'Paper code (TJ, TA, etc.)',
    name VARCHAR(100) NOT NULL,
    business_unit VARCHAR(50) COMMENT 'Business unit/division',

    is_active BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_code (code),
    INDEX idx_business_unit (business_unit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Publications (newspapers)';


-- Editions (morning, evening, weekend, etc.)
CREATE TABLE IF NOT EXISTS editions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(3) UNIQUE NOT NULL,
    name VARCHAR(50) NOT NULL,
    description TEXT,

    is_active BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Publication editions';


-- Issue codes (days of week published)
CREATE TABLE IF NOT EXISTS issue_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(3) UNIQUE NOT NULL COMMENT '7D, 5D, WO, etc.',
    description VARCHAR(100),
    days_per_week TINYINT COMMENT 'Number of issues per week',

    -- Days published (bitmap or boolean flags)
    publish_sunday BOOLEAN DEFAULT FALSE,
    publish_monday BOOLEAN DEFAULT FALSE,
    publish_tuesday BOOLEAN DEFAULT FALSE,
    publish_wednesday BOOLEAN DEFAULT FALSE,
    publish_thursday BOOLEAN DEFAULT FALSE,
    publish_friday BOOLEAN DEFAULT FALSE,
    publish_saturday BOOLEAN DEFAULT FALSE,

    is_active BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Issue codes - which days paper is published';


-- ABC Zones (Audit Bureau of Circulation zones)
CREATE TABLE IF NOT EXISTS abc_zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zone_code VARCHAR(10) UNIQUE NOT NULL,
    description VARCHAR(100),

    is_active BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='ABC circulation zones';


-- Rate Plans (pricing structures)
CREATE TABLE IF NOT EXISTS rate_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code INT NOT NULL,
    step INT DEFAULT 1 COMMENT 'Step in multi-step rate plan',
    next_step INT COMMENT 'Next step ID for progression',

    description VARCHAR(80),
    online_description VARCHAR(80),

    -- Subscription parameters
    edition_id INT COMMENT 'Which edition',
    issue_code_id INT COMMENT 'Which days',
    length INT COMMENT 'Subscription length',
    length_type VARCHAR(1) COMMENT 'D=days, W=weeks, M=months, Y=years',
    zone VARCHAR(4),
    delivery_method VARCHAR(2) COMMENT 'CARR, MAIL, INTE, etc.',
    payment_method VARCHAR(2) COMMENT 'Payment type required',
    easypay_method VARCHAR(1) COMMENT 'Auto-pay allowed',

    -- Pricing
    daily_rate DECIMAL(10,5) COMMENT 'Per-day cost',

    -- Status
    is_active BOOLEAN DEFAULT TRUE,
    inactive_date DATE COMMENT 'When deactivated',

    -- Grace period policies
    allow_single_copy_makeup VARCHAR(2),
    allow_econtent BOOLEAN DEFAULT FALSE,

    -- New subscription grace policy
    new_subscription_grace_policy VARCHAR(1),
    new_subscription_warning_days INT,
    new_subscription_courtesy_calls INT,
    new_subscription_stop_days INT,

    -- Renewal grace policy
    renewal_grace_policy VARCHAR(1),
    renewal_warning_days INT,
    renewal_courtesy_calls INT,
    renewal_stop_days INT,

    -- Billing cycle timing
    advance_billing BOOLEAN DEFAULT FALSE,
    arrears_billing_1 BOOLEAN DEFAULT FALSE,
    arrears_billing_2 BOOLEAN DEFAULT FALSE,
    arrears_billing_3 BOOLEAN DEFAULT FALSE,
    renewal_billing_1 BOOLEAN DEFAULT FALSE,
    renewal_billing_2 BOOLEAN DEFAULT FALSE,
    renewal_billing_3 BOOLEAN DEFAULT FALSE,

    advance_days INT,
    arrears_1_days INT,
    arrears_2_days INT,
    arrears_3_days INT,
    renewal_1_days INT,
    renewal_2_days INT,
    renewal_3_days INT,

    -- Financial limits
    new_balance_max DECIMAL(10,2),
    renewal_balance_max DECIMAL(10,2),

    -- Tax settings
    is_taxable BOOLEAN DEFAULT FALSE,
    abc_zone_id INT,

    -- Alternative settings
    alt_issue_code VARCHAR(3),
    alt_delivery_method VARCHAR(2),
    alt_abc_zone_id INT,

    -- GL accounts (would link to chart of accounts)
    promo_gl_account VARCHAR(4),
    sunday_revenue_account VARCHAR(4),
    monday_revenue_account VARCHAR(4),
    tuesday_revenue_account VARCHAR(4),
    wednesday_revenue_account VARCHAR(4),
    thursday_revenue_account VARCHAR(4),
    friday_revenue_account VARCHAR(4),
    saturday_revenue_account VARCHAR(4),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (edition_id) REFERENCES editions(id),
    FOREIGN KEY (issue_code_id) REFERENCES issue_codes(id),
    FOREIGN KEY (abc_zone_id) REFERENCES abc_zones(id),

    INDEX idx_code (code),
    INDEX idx_active (is_active),
    INDEX idx_edition (edition_id),
    INDEX idx_delivery (delivery_method)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Rate plans - pricing and subscription rules';


-- Retail Rate Amounts (actual dollar amounts by date)
CREATE TABLE IF NOT EXISTS rate_amounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rate_plan_id INT NOT NULL,

    effective_date DATE NOT NULL,
    delivery_expense DECIMAL(10,2) COMMENT 'Cost to deliver',

    -- Net rates by day
    net_rate DECIMAL(10,2),
    sunday_net DECIMAL(10,2),
    monday_net DECIMAL(10,2),
    tuesday_net DECIMAL(10,2),
    wednesday_net DECIMAL(10,2),
    thursday_net DECIMAL(10,2),
    friday_net DECIMAL(10,2),
    saturday_net DECIMAL(10,2),

    -- Payment method rates
    bank_draft_net DECIMAL(10,2),
    credit_card_net DECIMAL(10,2),
    full_rate DECIMAL(10,2),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (rate_plan_id) REFERENCES rate_plans(id) ON DELETE CASCADE,

    INDEX idx_rate_plan (rate_plan_id),
    INDEX idx_effective_date (effective_date),
    UNIQUE KEY uk_rate_date (rate_plan_id, effective_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Rate amounts by effective date';


-- Subscriptions (the actual subscription records)
CREATE TABLE IF NOT EXISTS subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,

    -- Core identification
    subscription_number VARCHAR(50) UNIQUE NOT NULL COMMENT 'Newzware SUB NUM',
    subscriber_id INT NOT NULL,
    publication_id INT NOT NULL,

    -- Subscription details
    number_of_copies INT DEFAULT 1,
    begin_date DATE NOT NULL,
    paid_through_date DATE,
    grace_end_date DATE,
    original_start_date DATE COMMENT 'Very first subscription date',

    -- Route assignment
    route_id INT COMMENT 'Assigned delivery route',

    -- Status
    status VARCHAR(2) COMMENT 'Active, suspended, etc.',
    in_grace BOOLEAN DEFAULT FALSE,
    ignore_grace BOOLEAN DEFAULT FALSE,

    -- Vacation status
    vacation_indicator INT COMMENT 'Link to vacation details',

    -- Rate and billing
    rate_plan_id INT NOT NULL,
    easypay_method VARCHAR(1) COMMENT 'Auto-pay type',
    billto_id INT COMMENT 'Who gets billed (if different)',
    tax_exempt BOOLEAN DEFAULT FALSE,

    -- Renewal notices
    no_renewal_notice BOOLEAN DEFAULT FALSE,
    renewal_notice_type VARCHAR(1),

    -- Complaint handling
    complaint_strategy VARCHAR(1),

    -- ABC and sources
    abc_zone_id INT,
    source_code VARCHAR(8) COMMENT 'How subscription was acquired',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE RESTRICT,
    FOREIGN KEY (publication_id) REFERENCES publications(id),
    FOREIGN KEY (rate_plan_id) REFERENCES rate_plans(id),
    FOREIGN KEY (abc_zone_id) REFERENCES abc_zones(id),

    INDEX idx_subscription_number (subscription_number),
    INDEX idx_subscriber (subscriber_id),
    INDEX idx_publication (publication_id),
    INDEX idx_status (status),
    INDEX idx_paid_through (paid_through_date),
    INDEX idx_begin_date (begin_date),
    INDEX idx_route (route_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Active subscriptions';


-- ============================================
-- VACATION MANAGEMENT
-- ============================================

-- Vacation Types
CREATE TABLE IF NOT EXISTS vacation_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(3) UNIQUE NOT NULL,
    description VARCHAR(40),

    -- Behavior flags
    allow_grace BOOLEAN DEFAULT FALSE COMMENT 'Extend grace period',
    allow_credit BOOLEAN DEFAULT FALSE COMMENT 'Credit account',
    allow_donate BOOLEAN DEFAULT FALSE COMMENT 'Donate to charity',

    -- Messaging
    repeat_message BOOLEAN DEFAULT FALSE,
    repeat_times INT DEFAULT 0,
    message_text VARCHAR(60),

    -- GL account for donations
    donation_gl_account VARCHAR(4),

    -- Remark codes
    remark_id INT COMMENT 'Remark when vacation starts',
    restart_remark_id INT COMMENT 'Remark when vacation ends',

    is_active BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Vacation types and policies';


-- Vacation Details (vacation holds)
CREATE TABLE IF NOT EXISTS vacations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subscription_id INT NOT NULL,
    vacation_type_id INT NOT NULL,

    begin_date DATE NOT NULL,
    end_date DATE NOT NULL,

    vacation_days INT COMMENT 'Total days on vacation',
    amortization_days INT COMMENT 'Days to credit',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
    FOREIGN KEY (vacation_type_id) REFERENCES vacation_types(id),

    INDEX idx_subscription (subscription_id),
    INDEX idx_dates (begin_date, end_date),
    INDEX idx_active_vacations (subscription_id, begin_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Vacation holds on subscriptions';


-- ============================================
-- DELIVERY ROUTES & DISTRIBUTORS
-- ============================================

-- Routes (delivery routes)
CREATE TABLE IF NOT EXISTS routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_code VARCHAR(8) UNIQUE NOT NULL,
    description VARCHAR(50),

    route_type VARCHAR(1) COMMENT 'Carrier, motor, etc.',
    pio_only BOOLEAN DEFAULT FALSE COMMENT 'Paid-in-office only',

    is_active BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_code (route_code),
    INDEX idx_type (route_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Delivery routes';


-- Districts (geographic delivery districts)
CREATE TABLE IF NOT EXISTS districts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    district_code INT UNIQUE NOT NULL,
    name VARCHAR(30),

    -- District manager contact
    phone VARCHAR(14),
    email VARCHAR(60),
    zone INT COMMENT 'Geographic zone',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Delivery districts';


-- Route Instances (route assignments with specific settings)
CREATE TABLE IF NOT EXISTS route_instances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    distributor_id INT NOT NULL,
    route_id INT NOT NULL,
    district_id INT,

    is_active BOOLEAN DEFAULT TRUE,

    -- Billing cycle
    billing_cycle VARCHAR(1) COMMENT 'When billed',

    -- Publication specifics
    edition_id INT,
    issue_code_id INT,

    -- Zone overrides
    wholesale_rate_zone VARCHAR(8),
    abc_zone_id INT,
    force_abc BOOLEAN DEFAULT FALSE,

    -- Billing settings
    separate_billing BOOLEAN DEFAULT FALSE,
    billing_method VARCHAR(1),
    pio_method VARCHAR(1) COMMENT 'Paid-in-office handling',

    -- Date range
    start_date DATE,
    stop_date DATE,

    -- Group and accounting
    invoice_group_code VARCHAR(8),
    issue_1099 BOOLEAN DEFAULT FALSE,

    -- Delivery settings
    delivery_zip VARCHAR(7),
    bundle_wrap_type VARCHAR(1),

    -- Allowances
    allow_bad_debt_credit BOOLEAN DEFAULT FALSE,
    allow_returns BOOLEAN DEFAULT FALSE,
    allow_advance_run_allowance BOOLEAN DEFAULT FALSE,

    -- Draw quantities by day
    holiday_draw INT DEFAULT 0,
    sunday_draw INT DEFAULT 0,
    monday_draw INT DEFAULT 0,
    tuesday_draw INT DEFAULT 0,
    wednesday_draw INT DEFAULT 0,
    thursday_draw INT DEFAULT 0,
    friday_draw INT DEFAULT 0,
    saturday_draw INT DEFAULT 0,
    days_of_week VARCHAR(7) COMMENT 'Bitmap of active days',

    -- Other settings
    credit_flag BOOLEAN DEFAULT FALSE,
    agent_code VARCHAR(8),
    delivery_credit_zone VARCHAR(8),
    net_sales_zone VARCHAR(8),
    is_dispatchable BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (distributor_id) REFERENCES subscribers(id),
    FOREIGN KEY (route_id) REFERENCES routes(id),
    FOREIGN KEY (district_id) REFERENCES districts(id),
    FOREIGN KEY (edition_id) REFERENCES editions(id),
    FOREIGN KEY (issue_code_id) REFERENCES issue_codes(id),
    FOREIGN KEY (abc_zone_id) REFERENCES abc_zones(id),

    INDEX idx_distributor (distributor_id),
    INDEX idx_route (route_id),
    INDEX idx_active (is_active),
    INDEX idx_dates (start_date, stop_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Route instance assignments to distributors';


-- Distributors/Carriers
CREATE TABLE IF NOT EXISTS distributors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subscriber_id INT NOT NULL COMMENT 'Links to subscribers table',

    -- Billing account
    billto_id INT,
    keep_credit_balance BOOLEAN DEFAULT FALSE,
    invoice_indicator BOOLEAN DEFAULT FALSE,

    -- Tax info
    tax_id VARCHAR(15),
    supplier_number VARCHAR(20),

    -- Flags
    will_substitute BOOLEAN DEFAULT FALSE,
    issue_1099 BOOLEAN DEFAULT FALSE,
    birthdate DATE,

    -- Account balances
    balance_forward DECIMAL(10,2) DEFAULT 0,
    uninvoiced_amount DECIMAL(10,2) DEFAULT 0,
    balance_current DECIMAL(10,2) DEFAULT 0,
    balance_30_days DECIMAL(10,2) DEFAULT 0,
    balance_60_days DECIMAL(10,2) DEFAULT 0,
    balance_90_days DECIMAL(10,2) DEFAULT 0,
    balance_120_days DECIMAL(10,2) DEFAULT 0,
    balance_150_days DECIMAL(10,2) DEFAULT 0,
    balance_180_days DECIMAL(10,2) DEFAULT 0,

    -- Statement tracking
    statement_count INT DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id) ON DELETE RESTRICT,

    INDEX idx_subscriber (subscriber_id),
    INDEX idx_tax_id (tax_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Distributors/carriers who deliver papers';


-- ============================================
-- TRANSACTION HISTORY
-- ============================================

-- Remark codes (transaction types/reasons)
CREATE TABLE IF NOT EXISTS remark_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(8) UNIQUE NOT NULL,
    description VARCHAR(60),

    remark_type VARCHAR(1) COMMENT 'Category of remark',
    action_id INT COMMENT 'What action this triggers',

    -- Display settings
    show_to_carrier BOOLEAN DEFAULT FALSE,
    allow_district_change BOOLEAN DEFAULT FALSE,
    display_message BOOLEAN DEFAULT FALSE,
    repeat_days INT DEFAULT 0,

    is_active BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_code (code),
    INDEX idx_type (remark_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Transaction remark codes';


-- Subscription transactions (history log)
CREATE TABLE IF NOT EXISTS subscription_transactions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,

    subscription_id INT NOT NULL,
    subscriber_id INT NOT NULL,
    address_sequence_id INT COMMENT 'Address at time of transaction',

    transaction_date DATE NOT NULL,
    entry_datetime DATETIME NOT NULL,

    user_id INT COMMENT 'Who made the change',
    script_id INT COMMENT 'Automated script ID',

    -- Transaction details
    action_id INT,
    remark_id INT,
    custom_remark BOOLEAN DEFAULT FALSE,

    -- Processing
    is_processed BOOLEAN DEFAULT FALSE,
    promotion_code VARCHAR(8),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id),
    FOREIGN KEY (subscriber_id) REFERENCES subscribers(id),
    FOREIGN KEY (remark_id) REFERENCES remark_codes(id),

    INDEX idx_subscription (subscription_id),
    INDEX idx_subscriber (subscriber_id),
    INDEX idx_transaction_date (transaction_date),
    INDEX idx_entry_datetime (entry_datetime),
    INDEX idx_processed (is_processed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Subscription transaction history';


-- ============================================
-- DRAW/INVENTORY MANAGEMENT
-- ============================================

-- Draw Keys (draw categorization)
CREATE TABLE IF NOT EXISTS draw_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,

    draw_type VARCHAR(2) COMMENT 'Type of draw',
    abc_zone_id INT,
    is_paid BOOLEAN DEFAULT FALSE,
    state VARCHAR(4),
    zip VARCHAR(10),
    county VARCHAR(3),
    in_grace BOOLEAN DEFAULT FALSE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_draw_type (draw_type),
    INDEX idx_paid (is_paid),
    INDEX idx_state (state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Draw categorization keys';


-- Draw Details (daily draw records)
CREATE TABLE IF NOT EXISTS draw_details (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,

    draw_date DATE NOT NULL,
    copies INT NOT NULL,

    distributor_invoice INT,
    subscription_invoice INT,

    route_instance_id INT,
    subscription_id INT,
    draw_key_id INT,

    percent_of_full INT COMMENT 'Percentage paid',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (route_instance_id) REFERENCES route_instances(id),
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id),
    FOREIGN KEY (draw_key_id) REFERENCES draw_keys(id),

    INDEX idx_draw_date (draw_date),
    INDEX idx_route_instance (route_instance_id),
    INDEX idx_subscription (subscription_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Daily draw/circulation records';


-- ============================================
-- FINANCIAL/ACCOUNTING
-- ============================================

-- Chart of Accounts
CREATE TABLE IF NOT EXISTS chart_of_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_code VARCHAR(4) UNIQUE NOT NULL,

    -- Account breakdown
    company VARCHAR(3),
    business_unit VARCHAR(3),
    department VARCHAR(3),
    account VARCHAR(5),
    product VARCHAR(4),

    description VARCHAR(60),

    -- Balances
    period_to_date_total DECIMAL(12,2) DEFAULT 0,
    sublevel_total DECIMAL(12,2) DEFAULT 0,
    year_to_date_total DECIMAL(12,2) DEFAULT 0,

    account_type INT COMMENT 'Asset, liability, revenue, expense',
    flex_account VARCHAR(80) COMMENT 'Flexible account structure',

    is_active BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_code (account_code),
    INDEX idx_type (account_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Chart of accounts';


-- Invoice Groups (billing groups)
CREATE TABLE IF NOT EXISTS invoice_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_code VARCHAR(3) UNIQUE NOT NULL,
    description VARCHAR(30),

    -- GL Accounts
    ar_account_id INT COMMENT 'Accounts receivable',
    sunday_revenue_account_id INT,
    monday_revenue_account_id INT,
    tuesday_revenue_account_id INT,
    wednesday_revenue_account_id INT,
    thursday_revenue_account_id INT,
    friday_revenue_account_id INT,
    saturday_revenue_account_id INT,
    deposit_account_id INT,
    deposit_refund_account_id INT,
    writeoff_account_id INT,
    credit_transfer_account_id INT,
    tax1_account_id INT,
    tax2_account_id INT,

    -- Statement formatting
    statement_formatter VARCHAR(150),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Invoice/billing groups';


-- Carrier Invoice Details
CREATE TABLE IF NOT EXISTS carrier_invoice_details (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,

    distributor_id INT NOT NULL,
    route_instance_id INT,

    draw_count DECIMAL(10,2),
    invoice_number INT,

    transaction_type INT COMMENT 'Charge, credit, payment',
    transaction_date DATE NOT NULL,

    gl_account_id INT,
    code VARCHAR(4),
    code_description VARCHAR(60),

    wholesale_rate_id INT,

    -- Amounts
    unpaid_amount DECIMAL(10,2),
    net_amount DECIMAL(10,2),
    tax_amount DECIMAL(10,2),

    receipt_number VARCHAR(16),
    session_id INT,
    group_id VARCHAR(8),

    state VARCHAR(1) COMMENT 'Invoice state',
    display_on_invoice BOOLEAN DEFAULT TRUE,

    inserts INT DEFAULT 0,
    publication_date DATE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (distributor_id) REFERENCES distributors(id),
    FOREIGN KEY (route_instance_id) REFERENCES route_instances(id),

    INDEX idx_distributor (distributor_id),
    INDEX idx_invoice (invoice_number),
    INDEX idx_transaction_date (transaction_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Carrier invoice line items';


-- Payment Log
CREATE TABLE IF NOT EXISTS payment_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,

    user_id VARCHAR(40),
    transaction_date DATE NOT NULL,
    batch_type VARCHAR(3),
    batch_amount DECIMAL(10,2),
    num_items INT,
    filename VARCHAR(256),

    entry_datetime DATETIME NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_transaction_date (transaction_date),
    INDEX idx_user (user_id),
    INDEX idx_batch_type (batch_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Payment batch processing log';


-- ============================================
-- VIEWS FOR COMPATIBILITY
-- ============================================

-- View that mimics the old subscriber_snapshots structure
-- This allows existing queries to continue working during migration
CREATE OR REPLACE VIEW v_subscriber_snapshots AS
SELECT
    s.id,
    CURRENT_DATE as snapshot_date,
    NOW() as import_timestamp,
    sub.subscription_number as sub_num,
    p.code as paper_code,
    p.name as paper_name,
    p.business_unit,

    CONCAT_WS(' ', s.first_name, s.last_name) as name,
    ri.route_id as route,

    CONCAT_WS(' ',
        a.primary_range,
        a.pre_direction,
        a.street_name,
        a.post_direction,
        a.suffix,
        a.unit,
        a.secondary_range
    ) as address,

    CONCAT_WS(' ', a.city, a.state, a.zip) as city_state_postal,

    rp.zone as rate_name,
    CONCAT(rp.length, ' ', rp.length_type) as subscription_length,
    rp.delivery_method as delivery_type,

    CASE
        WHEN sub.paid_through_date >= CURRENT_DATE THEN 'PAY'
        ELSE 'UNP'
    END as payment_status,

    sub.begin_date,
    sub.paid_through_date as paid_thru,
    rp.daily_rate,

    CASE WHEN v.id IS NOT NULL THEN 1 ELSE 0 END as on_vacation,

    -- New fields from Phase 1
    az.zone_code as abc,
    ic.code as issue_code,
    NULL as last_payment_amount,

    sp.phone_number as phone,
    se.email as email,
    NULL as login_id,
    NULL as last_login

FROM subscribers s
JOIN subscriptions sub ON sub.subscriber_id = s.id
JOIN publications p ON sub.publication_id = p.id
JOIN addresses a ON s.address_id = a.id
LEFT JOIN rate_plans rp ON sub.rate_plan_id = rp.id
LEFT JOIN route_instances ri ON sub.route_id = ri.id
LEFT JOIN abc_zones az ON sub.abc_zone_id = az.id
LEFT JOIN issue_codes ic ON rp.issue_code_id = ic.id
LEFT JOIN subscriber_phones sp ON s.id = sp.subscriber_id AND sp.is_primary = TRUE
LEFT JOIN subscriber_emails se ON s.id = se.subscriber_id AND se.is_primary = TRUE
LEFT JOIN vacations v ON sub.id = v.subscription_id
    AND CURRENT_DATE BETWEEN v.begin_date AND v.end_date
WHERE sub.status IN ('AC', 'A') -- Active subscriptions only
;

-- ============================================
-- END OF NORMALIZED SCHEMA
-- ============================================
