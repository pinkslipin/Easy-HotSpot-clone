-- ============================================================
-- MindSpace Workspace Hub — Seat Tracking System
-- Run this once against the `mikrotik` database.
-- ============================================================

-- ── Table ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `seats` (
    `id`          INT          NOT NULL AUTO_INCREMENT,
    `seat_number` VARCHAR(10)  NOT NULL                       COMMENT 'e.g. ME-01, IR-12',
    `zone`        VARCHAR(60)  NOT NULL                       COMMENT 'Human-readable zone name',
    `zone_code`   VARCHAR(5)   NOT NULL                       COMMENT 'Short prefix: ME, IR, CR, PR',
    `status`      ENUM('available','reserved','occupied')
                               NOT NULL DEFAULT 'available',
    `reserved_by`      INT          NULL                           COMMENT 'hotspot_users.user_id of the staff who reserved',
    `voucher_username`  VARCHAR(100) NULL                           COMMENT 'Hotspot username issued for this seat (set on walk-in / check-in)',
    `reserved_at` DATETIME     NULL,
    `expires_at`  DATETIME     NULL                           COMMENT 'Auto-release if NOW() > expires_at',
    `branch_id`   INT          NULL                           COMMENT 'Future: multi-branch support',
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_seat_number`  (`seat_number`),
    KEY `idx_status`             (`status`),
    KEY `idx_zone_code`          (`zone_code`),
    KEY `idx_reserved_by`        (`reserved_by`),
    KEY `idx_expires`            (`expires_at`),
    KEY `idx_branch`             (`branch_id`)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- ── Seed 48 seats (global sequential numbering) ──────────────
-- Main Entrance   :  1 –  4  (ME-01 .. ME-04)
-- Inner Room      :  5 – 24  (IR-05 .. IR-24)
-- Private Room    : 25 – 38  (PR-25 .. PR-38)
-- Conference Room : 39 – 48  (CR-39 .. CR-48)
INSERT IGNORE INTO `seats` (`seat_number`, `zone`, `zone_code`) VALUES

-- Main Entrance (seats 1-4)
('ME-01', 'Main Entrance', 'ME'),
('ME-02', 'Main Entrance', 'ME'),
('ME-03', 'Main Entrance', 'ME'),
('ME-04', 'Main Entrance', 'ME'),

-- Inner Room (seats 5-24)
('IR-05', 'Inner Room', 'IR'), ('IR-06', 'Inner Room', 'IR'),
('IR-07', 'Inner Room', 'IR'), ('IR-08', 'Inner Room', 'IR'),
('IR-09', 'Inner Room', 'IR'), ('IR-10', 'Inner Room', 'IR'),
('IR-11', 'Inner Room', 'IR'), ('IR-12', 'Inner Room', 'IR'),
('IR-13', 'Inner Room', 'IR'), ('IR-14', 'Inner Room', 'IR'),
('IR-15', 'Inner Room', 'IR'), ('IR-16', 'Inner Room', 'IR'),
('IR-17', 'Inner Room', 'IR'), ('IR-18', 'Inner Room', 'IR'),
('IR-19', 'Inner Room', 'IR'), ('IR-20', 'Inner Room', 'IR'),
('IR-21', 'Inner Room', 'IR'), ('IR-22', 'Inner Room', 'IR'),
('IR-23', 'Inner Room', 'IR'), ('IR-24', 'Inner Room', 'IR'),

-- Private Room (seats 25-38)
('PR-25', 'Private Room', 'PR'), ('PR-26', 'Private Room', 'PR'),
('PR-27', 'Private Room', 'PR'), ('PR-28', 'Private Room', 'PR'),
('PR-29', 'Private Room', 'PR'), ('PR-30', 'Private Room', 'PR'),
('PR-31', 'Private Room', 'PR'), ('PR-32', 'Private Room', 'PR'),
('PR-33', 'Private Room', 'PR'), ('PR-34', 'Private Room', 'PR'),
('PR-35', 'Private Room', 'PR'), ('PR-36', 'Private Room', 'PR'),
('PR-37', 'Private Room', 'PR'), ('PR-38', 'Private Room', 'PR'),

-- Conference Room (seats 39-48)
('CR-39', 'Conference Room', 'CR'), ('CR-40', 'Conference Room', 'CR'),
('CR-41', 'Conference Room', 'CR'), ('CR-42', 'Conference Room', 'CR'),
('CR-43', 'Conference Room', 'CR'), ('CR-44', 'Conference Room', 'CR'),
('CR-45', 'Conference Room', 'CR'), ('CR-46', 'Conference Room', 'CR'),
('CR-47', 'Conference Room', 'CR'), ('CR-48', 'Conference Room', 'CR');
