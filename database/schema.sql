-- ============================================================
-- Inter-University Cricket Tournament Management System
-- Database Schema (MySQL)
-- ============================================================

CREATE DATABASE IF NOT EXISTS cricket_tournament
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE cricket_tournament;

-- ------------------------------------------------------------
-- 1. USERS  (Admin / Coordinator / Team Manager)
-- ------------------------------------------------------------
CREATE TABLE users (
  user_id        INT AUTO_INCREMENT PRIMARY KEY,
  first_name     VARCHAR(50)  NOT NULL,
  last_name      VARCHAR(50)  NOT NULL,
  email          VARCHAR(100) NOT NULL UNIQUE,
  password_hash  VARCHAR(255) NOT NULL,
  role           ENUM('admin', 'coordinator', 'team_manager') NOT NULL,
  is_active      TINYINT(1)   NOT NULL DEFAULT 1,
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 2. SYSTEM SETTINGS  (registration deadline, team limit, fee, etc.)
--    Single-row config table managed by Admin
-- ------------------------------------------------------------
CREATE TABLE system_settings (
  setting_id            INT PRIMARY KEY DEFAULT 1,
  registration_deadline DATETIME     NULL,
  max_teams             INT          NOT NULL DEFAULT 8,
  max_players_per_team  INT          NOT NULL DEFAULT 16,
  registration_fee      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  points_win            INT          NOT NULL DEFAULT 2,
  points_tie            INT          NOT NULL DEFAULT 1,
  points_no_result      INT          NOT NULL DEFAULT 1,
  points_loss           INT          NOT NULL DEFAULT 0,
  updated_at             TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                                      ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_single_row CHECK (setting_id = 1)
) ENGINE=InnoDB;

INSERT INTO system_settings (setting_id) VALUES (1);

-- ------------------------------------------------------------
-- 3. VENUES
-- ------------------------------------------------------------
CREATE TABLE venues (
  venue_id    INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  city        VARCHAR(100) NOT NULL,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 4. TEAMS  (1 per university, owned by 1 Team Manager)
-- ------------------------------------------------------------
CREATE TABLE teams (
  team_id           INT AUTO_INCREMENT PRIMARY KEY,
  manager_user_id   INT NOT NULL,
  university_name   VARCHAR(100) NOT NULL UNIQUE,
  team_name         VARCHAR(100) NOT NULL,
  logo_path         VARCHAR(255) NULL,   -- relative path e.g. 'assets/logos/kelaniya.png'
  captain_player_id      INT NULL,
  vice_captain_player_id INT NULL,
  approval_status   ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  is_active         TINYINT(1) NOT NULL DEFAULT 1,
  created_at        TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP
                                ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_teams_manager
    FOREIGN KEY (manager_user_id) REFERENCES users(user_id)
    ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 5. PLAYERS  (max 16 per team, enforced at application layer)
-- ------------------------------------------------------------
CREATE TABLE players (
  player_id     INT AUTO_INCREMENT PRIMARY KEY,
  team_id       INT NOT NULL,
  first_name    VARCHAR(50) NOT NULL,
  last_name     VARCHAR(50) NOT NULL,
  contact_no    VARCHAR(20) NOT NULL,
  date_of_birth DATE NOT NULL,
  playing_role  ENUM('Batsman', 'Bowler', 'All-rounder', 'Wicketkeeper') NOT NULL,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP
                            ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_players_team
    FOREIGN KEY (team_id) REFERENCES teams(team_id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- Now that players exists, link captain/vice-captain FKs on teams
ALTER TABLE teams
  ADD CONSTRAINT fk_teams_captain
    FOREIGN KEY (captain_player_id) REFERENCES players(player_id)
    ON DELETE SET NULL,
  ADD CONSTRAINT fk_teams_vice_captain
    FOREIGN KEY (vice_captain_player_id) REFERENCES players(player_id)
    ON DELETE SET NULL;

-- ------------------------------------------------------------
-- 6. PAYMENTS  (1 registration payment per team, gateway-agnostic)
-- ------------------------------------------------------------
CREATE TABLE payments (
  payment_id       INT AUTO_INCREMENT PRIMARY KEY,
  team_id          INT NOT NULL UNIQUE,
  amount           DECIMAL(10,2) NOT NULL,
  payment_method   VARCHAR(50)   NULL,        -- e.g. 'card', 'bank', gateway name
  gateway_name     VARCHAR(50)   NULL,        -- e.g. 'payhere', 'stripe' (set later)
  transaction_id   VARCHAR(150)  NULL UNIQUE, -- gateway's reference id
  payment_status   ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending',
  is_active        TINYINT(1) NOT NULL DEFAULT 1,
  created_at       TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP  NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_payments_team
    FOREIGN KEY (team_id) REFERENCES teams(team_id)
    ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 7. MATCHES
-- ------------------------------------------------------------
CREATE TABLE matches (
  match_id        INT AUTO_INCREMENT PRIMARY KEY,
  team1_id        INT NOT NULL,
  team2_id        INT NOT NULL,
  venue_id        INT NOT NULL,
  scheduled_date  DATE NOT NULL,
  scheduled_time  TIME NOT NULL,
  stage           ENUM('group', 'semi_final', 'final') NOT NULL DEFAULT 'group',
  status          ENUM('scheduled', 'ongoing', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled',
  created_by      INT NOT NULL,   -- coordinator user_id
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_matches_team1 FOREIGN KEY (team1_id) REFERENCES teams(team_id),
  CONSTRAINT fk_matches_team2 FOREIGN KEY (team2_id) REFERENCES teams(team_id),
  CONSTRAINT fk_matches_venue FOREIGN KEY (venue_id) REFERENCES venues(venue_id),
  CONSTRAINT fk_matches_creator FOREIGN KEY (created_by) REFERENCES users(user_id),
  CONSTRAINT chk_different_teams CHECK (team1_id <> team2_id)
) ENGINE=InnoDB;

-- Prevent double-booking the same venue at the same date/time
CREATE UNIQUE INDEX uq_venue_datetime ON matches (venue_id, scheduled_date, scheduled_time);

-- ------------------------------------------------------------
-- 8. MATCH RESULTS  (final result only, entered after the match)
-- ------------------------------------------------------------
CREATE TABLE match_results (
  result_id         INT AUTO_INCREMENT PRIMARY KEY,
  match_id          INT NOT NULL UNIQUE,
  toss_winner_team_id INT NULL,
  toss_decision     ENUM('bat', 'bowl') NULL,
  team1_score       INT NOT NULL DEFAULT 0,
  team1_wickets     INT NOT NULL DEFAULT 0,
  team1_overs       DECIMAL(4,1) NOT NULL DEFAULT 0.0,
  team2_score       INT NOT NULL DEFAULT 0,
  team2_wickets     INT NOT NULL DEFAULT 0,
  team2_overs       DECIMAL(4,1) NOT NULL DEFAULT 0.0,
  result_type       ENUM('team1_won', 'team2_won', 'tie', 'no_result') NOT NULL,
  winner_team_id    INT NULL,
  win_margin_type   ENUM('runs', 'wickets') NULL,
  win_margin_value  INT NULL,
  entered_by        INT NOT NULL,  -- coordinator user_id
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_results_match FOREIGN KEY (match_id) REFERENCES matches(match_id) ON DELETE CASCADE,
  CONSTRAINT fk_results_toss_winner FOREIGN KEY (toss_winner_team_id) REFERENCES teams(team_id),
  CONSTRAINT fk_results_winner FOREIGN KEY (winner_team_id) REFERENCES teams(team_id),
  CONSTRAINT fk_results_entered_by FOREIGN KEY (entered_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- 9. POINTS TABLE  (1 row per team, recalculated when results are entered)
-- ------------------------------------------------------------
CREATE TABLE points_table (
  points_id       INT AUTO_INCREMENT PRIMARY KEY,
  team_id         INT NOT NULL UNIQUE,
  played          INT NOT NULL DEFAULT 0,
  won             INT NOT NULL DEFAULT 0,
  lost            INT NOT NULL DEFAULT 0,
  tied            INT NOT NULL DEFAULT 0,
  no_result       INT NOT NULL DEFAULT 0,
  points          INT NOT NULL DEFAULT 0,
  net_run_rate    DECIMAL(6,3) NOT NULL DEFAULT 0.000,
  updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                             ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_points_team FOREIGN KEY (team_id) REFERENCES teams(team_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Helpful indexes
-- ------------------------------------------------------------
CREATE INDEX idx_players_team ON players(team_id);
CREATE INDEX idx_matches_teams ON matches(team1_id, team2_id);
CREATE INDEX idx_matches_status ON matches(status);
CREATE INDEX idx_teams_manager ON teams(manager_user_id);

-- ------------------------------------------------------------
-- Seed: default Admin account
-- (password below is a placeholder hash — generate a real one with
--  PHP's password_hash() before using; documented in setup notes)
-- ------------------------------------------------------------
INSERT INTO users (first_name, last_name, email, password_hash, role)
VALUES ('System', 'Admin', 'admin@cricket-tournament.local', '$2y$10$REPLACE_WITH_REAL_HASH', 'admin');
