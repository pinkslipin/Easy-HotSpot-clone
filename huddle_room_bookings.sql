-- ============================================================
-- MindSpace Huddle Room Booking System
-- Run this once against the `mikrotik` database.
-- ============================================================

-- ── Table: Huddle Room Bookings ──────────────────────────────
CREATE TABLE IF NOT EXISTS `huddle_room_bookings` (
    `id`                    INT          NOT NULL AUTO_INCREMENT,
    `booking_ref`           VARCHAR(30)  NOT NULL UNIQUE            COMMENT 'Human-readable ref: HUB-20260408-001',
    `booking_date`          DATE         NOT NULL                   COMMENT 'Date of booking (YYYY-MM-DD)',
    `start_time`            TIME         NOT NULL                   COMMENT 'Start time (HH:MM:SS)',
    `end_time`              TIME         NOT NULL                   COMMENT 'End time (HH:MM:SS)',
    `duration_hours`        DECIMAL(3,1) NOT NULL                   COMMENT '1.0, 4.0, 8.0, etc.',
    
    `visitor_name`          VARCHAR(100)                            COMMENT 'Name of person booking',
    `visitor_email`         VARCHAR(100)                            COMMENT 'Contact email',
    `visitor_phone`         VARCHAR(20)                             COMMENT 'Contact phone',
    `company_name`          VARCHAR(100)                            COMMENT 'Company/Organization',
    
    `booked_occupancy`      INT          NOT NULL DEFAULT 5         COMMENT 'Occupancy at booking time',
    `final_occupancy`       INT          NULL                       COMMENT 'Final occupancy at check-in (null until checked in)',
    
    `base_price`            DECIMAL(10,2) NOT NULL                  COMMENT 'Price for base occupancy',
    `extra_price`           DECIMAL(10,2) NOT NULL DEFAULT 0        COMMENT 'Price for extra pax beyond base',
    `total_price`           DECIMAL(10,2) NOT NULL                  COMMENT 'base_price + extra_price',
    `paid_amount`           DECIMAL(10,2) NOT NULL DEFAULT 0        COMMENT 'Amount paid (for partial/installment)',
    
    `status`                ENUM('pending', 'confirmed', 'checked_in', 'completed', 'cancelled')
                            NOT NULL DEFAULT 'pending'              COMMENT 'Booking lifecycle',
    
    `checked_in_by`         INT          NULL                       COMMENT 'hotspot_users.user_id who checked in',
    `checked_in_at`         DATETIME     NULL                       COMMENT 'When check-in happened',
    `completed_at`          DATETIME     NULL                       COMMENT 'When session ended (auto or manual)',
    `cancelled_by`          INT          NULL                       COMMENT 'Who cancelled (null if system)',
    `cancellation_reason`   TEXT         NULL,
    
    `notes`                 TEXT         NULL                       COMMENT 'Internal staff notes',
    `created_at`            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_booking_ref` (`booking_ref`),
    KEY `idx_booking_date` (`booking_date`),
    KEY `idx_status` (`status`),
    KEY `idx_visitor_email` (`visitor_email`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ── Table: Huddle Room Pricing Rules ─────────────────────────
CREATE TABLE IF NOT EXISTS `huddle_room_pricing` (
    `id`                    INT         NOT NULL AUTO_INCREMENT,
    `occupancy_level`       INT         NOT NULL UNIQUE            COMMENT '5, 8, 10, etc.',
    `hourly_rate`           DECIMAL(10,2) NOT NULL                 COMMENT 'Price per hour at this occupancy',
    `daily_rate`            DECIMAL(10,2) NOT NULL                 COMMENT 'Price for full day (8 hours)',
    `active`                TINYINT(1) NOT NULL DEFAULT 1          COMMENT 'Is this tier active?',
    `created_at`            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_occupancy` (`occupancy_level`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ── Seed Pricing Rules ────────────────────────────────────────
INSERT IGNORE INTO `huddle_room_pricing` (`occupancy_level`, `hourly_rate`, `daily_rate`) VALUES
(5,  280.00, 2240.00),   -- 5 pax: 280/hr, 2240 for 8-hour day
(8,  485.00, 3880.00);   -- 8 pax: 485/hr, 3880 for 8-hour day

-- ── Table: Huddle Room Availability Rules ────────────────────
CREATE TABLE IF NOT EXISTS `huddle_room_availability` (
    `id`                    INT         NOT NULL AUTO_INCREMENT,
    `day_of_week`           INT         NOT NULL                   COMMENT '0=Sun, 1=Mon, ..., 6=Sat',
    `is_open`               TINYINT(1) NOT NULL DEFAULT 1          COMMENT 'Is room available this day?',
    `opens_at`              TIME        NOT NULL DEFAULT '00:00:00', 
    `closes_at`             TIME        NOT NULL DEFAULT '23:59:59',
    `created_at`            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_day_of_week` (`day_of_week`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ── Seed Availability (open 24/7 as per requirement) ──────────
INSERT IGNORE INTO `huddle_room_availability` (`day_of_week`, `is_open`, `opens_at`, `closes_at`) VALUES
(0, 1, '00:00:00', '23:59:59'),  -- Sunday
(1, 1, '00:00:00', '23:59:59'),  -- Monday
(2, 1, '00:00:00', '23:59:59'),  -- Tuesday
(3, 1, '00:00:00', '23:59:59'),  -- Wednesday
(4, 1, '00:00:00', '23:59:59'),  -- Thursday
(5, 1, '00:00:00', '23:59:59'),  -- Friday
(6, 1, '00:00:00', '23:59:59');  -- Saturday
