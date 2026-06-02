USE smartblood_connect;

INSERT INTO users (id, full_name, name, email, password_hash, role, phone, city, address, blood_group, latitude, longitude, status, last_login_at) VALUES
(1, 'System Administrator', 'System Administrator', 'admin@smartblood.test', '$2y$10$yvOabO/wz25qzwyJ9Z0d3.jnXfHiQ3hBUwBWMFBl29uIn0pQDxZAS', 'admin', '+9779800001000', 'Kathmandu', 'Central Blood Office, Teku', 'AB+', 27.7016, 85.3119, 'active', NOW()),
(2, 'Priya Sharma', 'Priya Sharma', 'patient@smartblood.test', '$2y$10$UtFk0vXaGgRdgpKHWJT9zup971g8Y0wrIpbB1JGe0Pk/yl3husiF6', 'patient', '+9779800002000', 'Lalitpur', 'Mahalaxmi, Lalitpur', 'A+', 27.6646, 85.3238, 'active', NOW()),
(3, 'Rabin Thapa', 'Rabin Thapa', 'donor@smartblood.test', '$2y$10$ZK5k9DRmzk7nJOacdYQ9FOJZfB2ROX/.Ox2b4Ebzj14o7lnhBY9s6', 'donor', '+9779800003000', 'Kathmandu', 'Baneshwor, Kathmandu', 'O+', 27.6942, 85.3420, 'active', NOW()),
(4, 'Milan Adhikari', 'Milan Adhikari', 'patient2@smartblood.test', '$2y$10$UtFk0vXaGgRdgpKHWJT9zup971g8Y0wrIpbB1JGe0Pk/yl3husiF6', 'patient', '+9779800002002', 'Kathmandu', 'Tokha, Kathmandu', 'B+', 27.7444, 85.3322, 'active', NOW()),
(5, 'Gita Shrestha', 'Gita Shrestha', 'patient3@smartblood.test', '$2y$10$UtFk0vXaGgRdgpKHWJT9zup971g8Y0wrIpbB1JGe0Pk/yl3husiF6', 'patient', '+9779800002003', 'Bhaktapur', 'Madhyapur Thimi', 'O-', 27.6774, 85.3875, 'active', NOW()),
(6, 'Ramesh Koirala', 'Ramesh Koirala', 'patient4@smartblood.test', '$2y$10$UtFk0vXaGgRdgpKHWJT9zup971g8Y0wrIpbB1JGe0Pk/yl3husiF6', 'patient', '+9779800002004', 'Kathmandu', 'Kalanki, Kathmandu', 'AB+', 27.6931, 85.2812, 'active', NOW()),
(7, 'Sita Poudel', 'Sita Poudel', 'patient5@smartblood.test', '$2y$10$UtFk0vXaGgRdgpKHWJT9zup971g8Y0wrIpbB1JGe0Pk/yl3husiF6', 'patient', '+9779800002005', 'Lalitpur', 'Kupandole, Lalitpur', 'A-', 27.6856, 85.3188, 'active', NOW()),
(8, 'Anisha Karki', 'Anisha Karki', 'donor2@smartblood.test', '$2y$10$ZK5k9DRmzk7nJOacdYQ9FOJZfB2ROX/.Ox2b4Ebzj14o7lnhBY9s6', 'donor', '+9779800003101', 'Bhaktapur', 'Suryabinayak, Bhaktapur', 'A+', 27.6739, 85.4298, 'active', NOW()),
(9, 'Suman Basnet', 'Suman Basnet', 'donor3@smartblood.test', '$2y$10$ZK5k9DRmzk7nJOacdYQ9FOJZfB2ROX/.Ox2b4Ebzj14o7lnhBY9s6', 'donor', '+9779800003102', 'Kathmandu', 'Kalanki, Kathmandu', 'B+', 27.6932, 85.2816, 'active', NOW()),
(10, 'Kriti Lamichhane', 'Kriti Lamichhane', 'donor4@smartblood.test', '$2y$10$ZK5k9DRmzk7nJOacdYQ9FOJZfB2ROX/.Ox2b4Ebzj14o7lnhBY9s6', 'donor', '+9779800003103', 'Lalitpur', 'Kupondole, Lalitpur', 'O-', 27.6787, 85.3167, 'active', NOW()),
(11, 'Aarav Rai', 'Aarav Rai', 'donor5@smartblood.test', '$2y$10$ZK5k9DRmzk7nJOacdYQ9FOJZfB2ROX/.Ox2b4Ebzj14o7lnhBY9s6', 'donor', '+9779800003104', 'Kathmandu', 'Kirtipur, Kathmandu', 'AB+', 27.6580, 85.2890, 'active', NOW()),
(12, 'Nima Gurung', 'Nima Gurung', 'donor6@smartblood.test', '$2y$10$ZK5k9DRmzk7nJOacdYQ9FOJZfB2ROX/.Ox2b4Ebzj14o7lnhBY9s6', 'donor', '+9779800003105', 'Lalitpur', 'Jawalakhel, Lalitpur', 'A-', 27.6711, 85.3186, 'active', NOW()),
(13, 'Prabin Bhattarai', 'Prabin Bhattarai', 'donor7@smartblood.test', '$2y$10$ZK5k9DRmzk7nJOacdYQ9FOJZfB2ROX/.Ox2b4Ebzj14o7lnhBY9s6', 'donor', '+9779800003106', 'Kathmandu', 'Boudha, Kathmandu', 'O+', 27.7215, 85.3618, 'active', NOW()),
(14, 'Manisha Shahi', 'Manisha Shahi', 'donor8@smartblood.test', '$2y$10$ZK5k9DRmzk7nJOacdYQ9FOJZfB2ROX/.Ox2b4Ebzj14o7lnhBY9s6', 'donor', '+9779800003107', 'Kathmandu', 'Baluwatar, Kathmandu', 'B-', 27.7268, 85.3312, 'active', NOW()),
(15, 'Rojina Maharjan', 'Rojina Maharjan', 'donor9@smartblood.test', '$2y$10$ZK5k9DRmzk7nJOacdYQ9FOJZfB2ROX/.Ox2b4Ebzj14o7lnhBY9s6', 'donor', '+9779800003108', 'Lalitpur', 'Satdobato, Lalitpur', 'AB-', 27.6566, 85.3249, 'active', NOW()),
(16, 'Bikash KC', 'Bikash KC', 'donor10@smartblood.test', '$2y$10$ZK5k9DRmzk7nJOacdYQ9FOJZfB2ROX/.Ox2b4Ebzj14o7lnhBY9s6', 'donor', '+9779800003109', 'Kathmandu', 'Chabahil, Kathmandu', 'A+', 27.7174, 85.3444, 'active', NOW()),
(17, 'Sunita Acharya', 'Sunita Acharya', 'donor11@smartblood.test', '$2y$10$ZK5k9DRmzk7nJOacdYQ9FOJZfB2ROX/.Ox2b4Ebzj14o7lnhBY9s6', 'donor', '+9779800003110', 'Kathmandu', 'Maharajgunj, Kathmandu', 'O-', 27.7362, 85.3338, 'active', NOW()),
(18, 'Gopal Gautam', 'Gopal Gautam', 'donor12@smartblood.test', '$2y$10$ZK5k9DRmzk7nJOacdYQ9FOJZfB2ROX/.Ox2b4Ebzj14o7lnhBY9s6', 'donor', '+9779800003111', 'Kathmandu', 'New Baneshwor, Kathmandu', 'B+', 27.6948, 85.3363, 'active', NOW()),
(19, 'Nabin Timilsina', 'Nabin Timilsina', 'donor13@smartblood.test', '$2y$10$ZK5k9DRmzk7nJOacdYQ9FOJZfB2ROX/.Ox2b4Ebzj14o7lnhBY9s6', 'donor', '+9779800003112', 'Bhaktapur', 'Kausaltar, Bhaktapur', 'A-', 27.6723, 85.3496, 'active', NOW()),
(20, 'Puja Neupane', 'Puja Neupane', 'donor14@smartblood.test', '$2y$10$ZK5k9DRmzk7nJOacdYQ9FOJZfB2ROX/.Ox2b4Ebzj14o7lnhBY9s6', 'donor', '+9779800003113', 'Kathmandu', 'Koteshwor, Kathmandu', 'O+', 27.6788, 85.3494, 'active', NOW()),
(21, 'Rijan Joshi', 'Rijan Joshi', 'donor15@smartblood.test', '$2y$10$ZK5k9DRmzk7nJOacdYQ9FOJZfB2ROX/.Ox2b4Ebzj14o7lnhBY9s6', 'donor', '+9779800003114', 'Kathmandu', 'Naxal, Kathmandu', 'AB+', 27.7178, 85.3329, 'active', NOW()),
(22, 'Karuna Khadka', 'Karuna Khadka', 'donor16@smartblood.test', '$2y$10$ZK5k9DRmzk7nJOacdYQ9FOJZfB2ROX/.Ox2b4Ebzj14o7lnhBY9s6', 'donor', '+9779800003115', 'Lalitpur', 'Gwarko, Lalitpur', 'B+', 27.6669, 85.3344, 'active', NOW()),
(23, 'Deepak Bista', 'Deepak Bista', 'donor17@smartblood.test', '$2y$10$ZK5k9DRmzk7nJOacdYQ9FOJZfB2ROX/.Ox2b4Ebzj14o7lnhBY9s6', 'donor', '+9779800003116', 'Kathmandu', 'Samakhusi, Kathmandu', 'O-', 27.7316, 85.3121, 'active', NOW()),
(24, 'Asha Regmi', 'Asha Regmi', 'donor18@smartblood.test', '$2y$10$ZK5k9DRmzk7nJOacdYQ9FOJZfB2ROX/.Ox2b4Ebzj14o7lnhBY9s6', 'donor', '+9779800003117', 'Kathmandu', 'Budhanilkantha, Kathmandu', 'A+', 27.7667, 85.3666, 'active', NOW()),
(25, 'Roshan Lama', 'Roshan Lama', 'donor19@smartblood.test', '$2y$10$ZK5k9DRmzk7nJOacdYQ9FOJZfB2ROX/.Ox2b4Ebzj14o7lnhBY9s6', 'donor', '+9779800003118', 'Lalitpur', 'Imadol, Lalitpur', 'B-', 27.6455, 85.3432, 'active', NOW()),
(26, 'Sujan Chaudhary', 'Sujan Chaudhary', 'donor20@smartblood.test', '$2y$10$ZK5k9DRmzk7nJOacdYQ9FOJZfB2ROX/.Ox2b4Ebzj14o7lnhBY9s6', 'donor', '+9779800003119', 'Kathmandu', 'Gongabu, Kathmandu', 'O+', 27.7396, 85.3095, 'active', NOW());

INSERT INTO admins (id, user_id, designation) VALUES
(1, 1, 'Blood Bank Administrator');

INSERT INTO patients (id, user_id, blood_group, age, gender, date_of_birth, emergency_contact, hospital_preference, city, address, latitude, longitude, notes) VALUES
(1, 2, 'A+', 29, 'female', '1997-04-18', '+9779800002100', 'Patan Hospital', 'Lalitpur', 'Mahalaxmi, Lalitpur', 27.6646, 85.3238, 'Recurring transfusion case.'),
(2, 4, 'B+', 34, 'male', '1992-02-07', '+9779800002102', 'Bir Hospital', 'Kathmandu', 'Tokha, Kathmandu', 27.7444, 85.3322, 'Emergency trauma support.'),
(3, 5, 'O-', 41, 'female', '1985-11-12', '+9779800002103', 'Civil Hospital', 'Bhaktapur', 'Madhyapur Thimi', 27.6774, 85.3875, 'Scheduled surgery support.'),
(4, 6, 'AB+', 38, 'male', '1988-09-21', '+9779800002104', 'Norvic Hospital', 'Kathmandu', 'Kalanki, Kathmandu', 27.6931, 85.2812, 'Routine management case.'),
(5, 7, 'A-', 31, 'female', '1995-06-11', '+9779800002105', 'Teaching Hospital Maharajgunj', 'Lalitpur', 'Kupandole, Lalitpur', 27.6856, 85.3188, 'Pre-op reserve blood request history.');

INSERT INTO donors (id, user_id, blood_group, age, date_of_birth, weight, medical_condition_status, medical_condition, availability_status, available_status, city, address, latitude, longitude, location_updated_at, last_donation_date, is_verified, is_eligible, past_donations, total_donations, response_rate) VALUES
(1, 3, 'O+', 27, '1999-09-20', 68.5, 'healthy', 'healthy', 'available', 'available', 'Kathmandu', 'Baneshwor, Kathmandu', 27.6942, 85.3420, NOW(), '2025-12-28', 1, 1, 4, 4, 68.00),
(2, 8, 'A+', 30, '1996-03-11', 56.0, 'healthy', 'healthy', 'available', 'available', 'Bhaktapur', 'Suryabinayak, Bhaktapur', 27.6739, 85.4298, NOW(), '2025-10-01', 1, 1, 8, 8, 75.00),
(3, 9, 'B+', 33, '1993-01-10', 74.0, 'healthy', 'healthy', 'available', 'available', 'Kathmandu', 'Kalanki, Kathmandu', 27.6932, 85.2816, NOW(), '2026-01-08', 1, 1, 10, 10, 81.00),
(4, 10, 'O-', 25, '2001-06-01', 52.4, 'healthy', 'healthy', 'available', 'available', 'Lalitpur', 'Kupondole, Lalitpur', 27.6787, 85.3167, NOW(), '2025-11-12', 1, 1, 3, 3, 59.00),
(5, 11, 'AB+', 37, '1989-09-14', 78.0, 'healthy', 'healthy', 'busy', 'busy', 'Kathmandu', 'Kirtipur, Kathmandu', 27.6580, 85.2890, NOW(), '2025-08-20', 1, 1, 14, 14, 72.00),
(6, 12, 'A-', 29, '1997-12-26', 62.0, 'temporary_deferral', 'temporary_deferral', 'inactive', 'inactive', 'Lalitpur', 'Jawalakhel, Lalitpur', 27.6711, 85.3186, NOW(), '2025-09-18', 1, 0, 5, 5, 48.00),
(7, 13, 'O+', 32, '1994-05-18', 66.0, 'healthy', 'healthy', 'available', 'available', 'Kathmandu', 'Boudha, Kathmandu', 27.7215, 85.3618, NOW(), '2025-12-05', 0, 1, 2, 2, 40.00),
(8, 14, 'B-', 28, '1998-03-25', 57.5, 'healthy', 'healthy', 'available', 'available', 'Kathmandu', 'Baluwatar, Kathmandu', 27.7268, 85.3312, NOW(), '2025-10-21', 1, 1, 6, 6, 66.00),
(9, 15, 'AB-', 35, '1991-11-07', 63.2, 'healthy', 'healthy', 'available', 'available', 'Lalitpur', 'Satdobato, Lalitpur', 27.6566, 85.3249, NOW(), '2025-07-01', 1, 1, 9, 9, 77.00),
(10, 16, 'A+', 26, '2000-04-13', 59.8, 'healthy', 'healthy', 'available', 'available', 'Kathmandu', 'Chabahil, Kathmandu', 27.7174, 85.3444, NOW(), '2026-01-20', 1, 1, 4, 4, 64.00),
(11, 17, 'O-', 39, '1987-08-12', 71.1, 'healthy', 'healthy', 'available', 'available', 'Kathmandu', 'Maharajgunj, Kathmandu', 27.7362, 85.3338, NOW(), '2025-09-01', 1, 1, 13, 13, 83.00),
(12, 18, 'B+', 31, '1995-02-16', 61.0, 'healthy', 'healthy', 'busy', 'busy', 'Kathmandu', 'New Baneshwor, Kathmandu', 27.6948, 85.3363, NOW(), '2025-11-01', 1, 1, 7, 7, 63.00),
(13, 19, 'A-', 34, '1992-01-29', 60.3, 'healthy', 'healthy', 'available', 'available', 'Bhaktapur', 'Kausaltar, Bhaktapur', 27.6723, 85.3496, NOW(), '2025-10-08', 1, 1, 8, 8, 74.00),
(14, 20, 'O+', 29, '1997-09-09', 65.4, 'healthy', 'healthy', 'available', 'available', 'Kathmandu', 'Koteshwor, Kathmandu', 27.6788, 85.3494, NOW(), '2026-01-11', 1, 1, 5, 5, 70.00),
(15, 21, 'AB+', 30, '1996-12-10', 58.6, 'healthy', 'healthy', 'available', 'available', 'Kathmandu', 'Naxal, Kathmandu', 27.7178, 85.3329, NOW(), '2025-10-12', 1, 1, 4, 4, 61.00),
(16, 22, 'B+', 27, '1999-06-20', 55.0, 'healthy', 'healthy', 'available', 'available', 'Lalitpur', 'Gwarko, Lalitpur', 27.6669, 85.3344, NOW(), '2025-09-28', 1, 1, 3, 3, 57.00),
(17, 23, 'O-', 42, '1984-07-03', 72.2, 'healthy', 'healthy', 'inactive', 'inactive', 'Kathmandu', 'Samakhusi, Kathmandu', 27.7316, 85.3121, NOW(), '2025-05-16', 1, 0, 17, 17, 79.00),
(18, 24, 'A+', 24, '2002-05-21', 53.4, 'healthy', 'healthy', 'available', 'available', 'Kathmandu', 'Budhanilkantha, Kathmandu', 27.7667, 85.3666, NOW(), '2025-12-15', 1, 1, 2, 2, 52.00),
(19, 25, 'B-', 36, '1990-03-02', 69.0, 'healthy', 'healthy', 'available', 'available', 'Lalitpur', 'Imadol, Lalitpur', 27.6455, 85.3432, NOW(), '2025-08-24', 1, 1, 11, 11, 76.00),
(20, 26, 'O+', 40, '1986-04-30', 73.0, 'chronic_issue', 'chronic_issue', 'available', 'available', 'Kathmandu', 'Gongabu, Kathmandu', 27.7396, 85.3095, NOW(), '2025-10-10', 1, 0, 12, 12, 69.00);

INSERT INTO donor_locations (donor_id, label, address, city, latitude, longitude, is_primary, created_at, recorded_at) VALUES
(1, 'Home', 'Baneshwor, Kathmandu', 'Kathmandu', 27.6942, 85.3420, 1, NOW(), NOW()),
(2, 'Home', 'Suryabinayak, Bhaktapur', 'Bhaktapur', 27.6739, 85.4298, 1, NOW(), NOW()),
(3, 'Home', 'Kalanki, Kathmandu', 'Kathmandu', 27.6932, 85.2816, 1, NOW(), NOW()),
(4, 'Home', 'Kupondole, Lalitpur', 'Lalitpur', 27.6787, 85.3167, 1, NOW(), NOW()),
(5, 'Home', 'Kirtipur, Kathmandu', 'Kathmandu', 27.6580, 85.2890, 1, NOW(), NOW()),
(6, 'Home', 'Jawalakhel, Lalitpur', 'Lalitpur', 27.6711, 85.3186, 1, NOW(), NOW()),
(7, 'Home', 'Boudha, Kathmandu', 'Kathmandu', 27.7215, 85.3618, 1, NOW(), NOW()),
(8, 'Home', 'Baluwatar, Kathmandu', 'Kathmandu', 27.7268, 85.3312, 1, NOW(), NOW()),
(9, 'Home', 'Satdobato, Lalitpur', 'Lalitpur', 27.6566, 85.3249, 1, NOW(), NOW()),
(10, 'Home', 'Chabahil, Kathmandu', 'Kathmandu', 27.7174, 85.3444, 1, NOW(), NOW()),
(11, 'Home', 'Maharajgunj, Kathmandu', 'Kathmandu', 27.7362, 85.3338, 1, NOW(), NOW()),
(12, 'Home', 'New Baneshwor, Kathmandu', 'Kathmandu', 27.6948, 85.3363, 1, NOW(), NOW()),
(13, 'Home', 'Kausaltar, Bhaktapur', 'Bhaktapur', 27.6723, 85.3496, 1, NOW(), NOW()),
(14, 'Home', 'Koteshwor, Kathmandu', 'Kathmandu', 27.6788, 85.3494, 1, NOW(), NOW()),
(15, 'Home', 'Naxal, Kathmandu', 'Kathmandu', 27.7178, 85.3329, 1, NOW(), NOW()),
(16, 'Home', 'Gwarko, Lalitpur', 'Lalitpur', 27.6669, 85.3344, 1, NOW(), NOW()),
(17, 'Home', 'Samakhusi, Kathmandu', 'Kathmandu', 27.7316, 85.3121, 1, NOW(), NOW()),
(18, 'Home', 'Budhanilkantha, Kathmandu', 'Kathmandu', 27.7667, 85.3666, 1, NOW(), NOW()),
(19, 'Home', 'Imadol, Lalitpur', 'Lalitpur', 27.6455, 85.3432, 1, NOW(), NOW()),
(20, 'Home', 'Gongabu, Kathmandu', 'Kathmandu', 27.7396, 85.3095, 1, NOW(), NOW());

INSERT INTO blood_requests (id, patient_id, blood_group, units_needed, units_fulfilled, hospital_name, hospital_address, hospital_city, city, hospital_latitude, hospital_longitude, latitude, longitude, urgency, notes, status, request_status, approved_by, approved_at, created_at) VALUES
(1, 1, 'A+', 3, 1, 'Patan Hospital', 'Lagankhel Rd, Lalitpur', 'Lalitpur', 'Lalitpur', 27.6679, 85.3206, 27.6679, 85.3206, 'critical', 'Urgent surgery support needed.', 'matched', 'matched', 1, NOW(), DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, 2, 'B+', 4, 2, 'Bir Hospital', 'Kanti Path, Kathmandu', 'Kathmandu', 'Kathmandu', 27.7049, 85.3133, 27.7049, 85.3133, 'high', 'Accident trauma requirement.', 'partially_fulfilled', 'partially_fulfilled', 1, NOW(), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 3, 'O-', 2, 0, 'Civil Hospital', 'Minbhawan, Kathmandu', 'Kathmandu', 'Kathmandu', 27.6927, 85.3367, 27.6927, 85.3367, 'high', 'Blood required within 6 hours.', 'pending', 'pending', 1, NOW(), DATE_SUB(NOW(), INTERVAL 6 HOUR)),
(4, 4, 'AB+', 1, 0, 'Norvic Hospital', 'Thapathali, Kathmandu', 'Kathmandu', 'Kathmandu', 27.6892, 85.3178, 27.6892, 85.3178, 'medium', 'Routine planned transfusion.', 'pending', 'pending', 1, NOW(), DATE_SUB(NOW(), INTERVAL 5 HOUR)),
(5, 5, 'A-', 2, 0, 'Teaching Hospital Maharajgunj', 'Maharajgunj Rd, Kathmandu', 'Kathmandu', 'Kathmandu', 27.7361, 85.3318, 27.7361, 85.3318, 'critical', 'Emergency ICU support.', 'pending', 'pending', 1, NOW(), DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(6, 1, 'O+', 2, 2, 'Grande Hospital', 'Dhapasi, Kathmandu', 'Kathmandu', 'Kathmandu', 27.7442, 85.3270, 27.7442, 85.3270, 'medium', 'Completed request history.', 'fulfilled', 'fulfilled', 1, DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY)),
(7, 2, 'B-', 1, 0, 'Kathmandu Medical College', 'Sinamangal, Kathmandu', 'Kathmandu', 'Kathmandu', 27.6967, 85.3535, 27.6967, 85.3535, 'low', 'Backup reserve request.', 'pending', 'pending', 1, NOW(), DATE_SUB(NOW(), INTERVAL 2 HOUR));

INSERT INTO blood_inventory (id, blood_group, donor_id, request_id, quantity_units, expiry_date, status, collected_at, created_by, created_at) VALUES
(1, 'A+', 2, NULL, 2, DATE_ADD(CURDATE(), INTERVAL 20 DAY), 'available', DATE_SUB(CURDATE(), INTERVAL 9 DAY), 1, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(2, 'B+', 3, 2, 1, DATE_ADD(CURDATE(), INTERVAL 18 DAY), 'available', DATE_SUB(CURDATE(), INTERVAL 7 DAY), 1, DATE_SUB(NOW(), INTERVAL 7 DAY)),
(3, 'O-', 4, NULL, 1, DATE_ADD(CURDATE(), INTERVAL 15 DAY), 'available', DATE_SUB(CURDATE(), INTERVAL 5 DAY), 1, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(4, 'AB+', 15, NULL, 2, DATE_ADD(CURDATE(), INTERVAL 24 DAY), 'available', DATE_SUB(CURDATE(), INTERVAL 3 DAY), 1, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(5, 'O+', 1, 6, 0, DATE_ADD(CURDATE(), INTERVAL 12 DAY), 'issued', DATE_SUB(CURDATE(), INTERVAL 18 DAY), 1, DATE_SUB(NOW(), INTERVAL 18 DAY)),
(6, 'A-', 13, NULL, 1, DATE_ADD(CURDATE(), INTERVAL 16 DAY), 'available', DATE_SUB(CURDATE(), INTERVAL 4 DAY), 1, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(7, 'B-', 19, NULL, 1, DATE_ADD(CURDATE(), INTERVAL 11 DAY), 'available', DATE_SUB(CURDATE(), INTERVAL 6 DAY), 1, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(8, 'O-', 11, NULL, 1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'expired', DATE_SUB(CURDATE(), INTERVAL 45 DAY), 1, DATE_SUB(NOW(), INTERVAL 45 DAY));

INSERT INTO inventory_transactions (inventory_id, action, quantity_units, reference_type, reference_id, notes, performed_by, created_at) VALUES
(1, 'add', 2, 'manual', 1, 'Initial A+ stock', 1, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(2, 'add', 2, 'manual', 1, 'Collected B+ units', 1, DATE_SUB(NOW(), INTERVAL 7 DAY)),
(2, 'issue', 1, 'blood_request', 2, 'Issued to BR-2', 1, DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 'add', 1, 'manual', 1, 'O- reserve', 1, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(4, 'add', 2, 'manual', 1, 'AB+ reserve', 1, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(5, 'add', 2, 'manual', 1, 'O+ stock old', 1, DATE_SUB(NOW(), INTERVAL 18 DAY)),
(5, 'issue', 2, 'blood_request', 6, 'Issued for fulfilled request', 1, DATE_SUB(NOW(), INTERVAL 8 DAY)),
(8, 'expire', 1, 'manual', 1, 'Expired O- unit', 1, NOW());

INSERT INTO donor_notifications (request_id, blood_request_id, donor_id, probability_score, predicted_probability, matching_score, distance_km, status, message, responded_at, created_at) VALUES
(1, 1, 1, 0.7420, 0.7420, 82.40, 3.10, 'accepted', 'Critical A+ request near Patan Hospital.', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),
(1, 1, 2, 0.6920, 0.6920, 74.70, 12.30, 'pending', 'Critical A+ request near Patan Hospital.', NULL, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, 2, 3, 0.7910, 0.7910, 85.20, 4.20, 'accepted', 'High priority B+ request at Bir Hospital.', DATE_SUB(NOW(), INTERVAL 8 HOUR), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(2, 2, 12, 0.6140, 0.6140, 63.10, 5.90, 'declined', 'High priority B+ request at Bir Hospital.', DATE_SUB(NOW(), INTERVAL 6 HOUR), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(3, 3, 4, 0.7030, 0.7030, 77.90, 2.80, 'pending', 'Urgent O- request at Civil Hospital.', NULL, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(3, 3, 11, 0.7340, 0.7340, 79.80, 5.10, 'pending', 'Urgent O- request at Civil Hospital.', NULL, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(5, 5, 11, 0.7650, 0.7650, 86.20, 0.80, 'accepted', 'Critical A- request at Teaching Hospital.', DATE_SUB(NOW(), INTERVAL 40 MINUTE), DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(5, 5, 13, 0.6120, 0.6120, 64.10, 8.40, 'pending', 'Critical A- request at Teaching Hospital.', NULL, DATE_SUB(NOW(), INTERVAL 2 HOUR));

INSERT INTO patient_notifications (patient_id, request_id, message, is_read, created_at) VALUES
(1, 1, 'Inventory issued 1 unit. Remaining units are being matched with donors.', 0, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(1, 1, 'Two nearby eligible donors were notified. Waiting for responses.', 0, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 2, 'Request partially fulfilled from inventory. Donor response received.', 1, DATE_SUB(NOW(), INTERVAL 10 HOUR)),
(3, 3, 'Your request is pending donor confirmation.', 0, DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(5, 5, 'A nearby donor accepted the notification.', 0, DATE_SUB(NOW(), INTERVAL 30 MINUTE));

INSERT INTO donation_appointments (request_id, donor_id, scheduled_at, location, status, notes, created_by, created_at) VALUES
(1, 1, DATE_ADD(NOW(), INTERVAL 1 DAY), 'Patan Hospital Blood Unit', 'scheduled', 'Bring donor ID card and recent reports.', 1, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(2, 3, DATE_ADD(NOW(), INTERVAL 6 HOUR), 'Bir Hospital Donor Desk', 'scheduled', 'Fast-track emergency slot.', 1, DATE_SUB(NOW(), INTERVAL 10 HOUR)),
(6, 1, DATE_SUB(NOW(), INTERVAL 8 DAY), 'Grande Hospital', 'completed', 'Donation completed successfully.', 1, DATE_SUB(NOW(), INTERVAL 9 DAY)),
(5, 11, DATE_ADD(NOW(), INTERVAL 4 HOUR), 'Teaching Hospital Donor Center', 'scheduled', 'Emergency response appointment.', 1, DATE_SUB(NOW(), INTERVAL 1 HOUR));

INSERT INTO blood_issuance (request_id, patient_id, inventory_id, units_issued, issued_by, issued_at, notes) VALUES
(2, 2, 2, 1, 1, DATE_SUB(NOW(), INTERVAL 1 DAY), 'Partial fulfillment from B+ stock'),
(6, 1, 5, 2, 1, DATE_SUB(NOW(), INTERVAL 8 DAY), 'Fulfilled completed request');

INSERT INTO ml_predictions (donor_id, request_id, model_name, probability_score, predicted_class, confidence_label, features_json, created_at) VALUES
(1, 1, 'logistic_regression', 0.7420, 'likely', 'High', JSON_OBJECT('age',27,'weight',68.5,'distance_km',3.10,'total_donations',4,'urgency_encoded',3), DATE_SUB(NOW(), INTERVAL 3 DAY)),
(2, 1, 'logistic_regression', 0.6920, 'likely', 'Medium', JSON_OBJECT('age',30,'weight',56.0,'distance_km',12.30,'total_donations',8,'urgency_encoded',3), DATE_SUB(NOW(), INTERVAL 3 DAY)),
(3, 2, 'logistic_regression', 0.7910, 'likely', 'High', JSON_OBJECT('age',33,'weight',74.0,'distance_km',4.20,'total_donations',10,'urgency_encoded',2), DATE_SUB(NOW(), INTERVAL 1 DAY)),
(11, 5, 'logistic_regression', 0.7650, 'likely', 'High', JSON_OBJECT('age',39,'weight',71.1,'distance_km',0.80,'total_donations',13,'urgency_encoded',3), DATE_SUB(NOW(), INTERVAL 2 HOUR));

INSERT INTO audit_logs (user_id, action, entity_type, entity_id, meta_json, created_at) VALUES
(1, 'seed_import', 'system', NULL, JSON_OBJECT('source', 'seed.sql', 'version', 'map-upgrade'), NOW()),
(2, 'request_created', 'blood_request', 1, JSON_OBJECT('urgency', 'critical'), DATE_SUB(NOW(), INTERVAL 3 DAY)),
(1, 'request_approved', 'blood_request', 1, JSON_OBJECT('approved_by', 'admin'), DATE_SUB(NOW(), INTERVAL 3 DAY)),
(1, 'donor_notifications_created', 'blood_request', 5, JSON_OBJECT('notifications', 2), DATE_SUB(NOW(), INTERVAL 2 HOUR));

INSERT INTO app_settings (setting_key, setting_value) VALUES
('app_title', 'SmartBlood Connect'),
('default_appointment_duration_minutes', '30'),
('low_stock_threshold', '5'),
('map_provider', 'osm'),
('map_default_lat', '27.7172'),
('map_default_lng', '85.3240');
