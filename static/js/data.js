// ============================================================
// SmartBlood Connect — Shared Data Store (data.js)
// ============================================================

const DB = {
  bloodGroups: ['A+','A-','B+','B-','AB+','AB-','O+','O-'],

  inventory: [
    { id: 'BI001', blood_type: 'A+',  units: 12, status: 'available' },
    { id: 'BI002', blood_type: 'A-',  units: 3,  status: 'available' },
    { id: 'BI003', blood_type: 'B+',  units: 8,  status: 'available' },
    { id: 'BI004', blood_type: 'B-',  units: 2,  status: 'available' },
    { id: 'BI005', blood_type: 'AB+', units: 5,  status: 'available' },
    { id: 'BI006', blood_type: 'AB-', units: 1,  status: 'available' },
    { id: 'BI007', blood_type: 'O+',  units: 14, status: 'available' },
    { id: 'BI008', blood_type: 'O-',  units: 2,  status: 'available' },
  ],

  requests: [
    { id: 'BR001', patient: 'John Doe', blood_type: 'A+', units: 2, hospital: 'City Medical Center', urgency: 'High', status: 'fulfilled', date: '2025-07-10', location: 'Kathmandu' },
    { id: 'BR002', patient: 'Sita Sharma', blood_type: 'O-', units: 1, hospital: 'B&B Hospital', urgency: 'Critical', status: 'pending', date: '2025-07-18', location: 'Lalitpur' },
    { id: 'BR003', patient: 'Ram Thapa', blood_type: 'B+', units: 3, hospital: 'Teaching Hospital', urgency: 'Routine', status: 'waiting_for_donor', date: '2025-07-20', location: 'Bhaktapur' },
    { id: 'BR004', patient: 'Priya KC', blood_type: 'AB+', units: 2, hospital: 'Grande Hospital', urgency: 'High', status: 'approved', date: '2025-07-22', location: 'Kathmandu' },
  ],

  donors: [
    { id: 'D001', name: 'Kiran Budhathoki', blood_type: 'O+',  weight: 72, age: 24, last_donation: '2025-01-12', eligible: true, location: 'Kathmandu', response_rate: 0.82, total_donations: 7 },
    { id: 'D002', name: 'Aarav Shrestha',   blood_type: 'A+',  weight: 68, age: 29, last_donation: '2025-03-05', eligible: true, location: 'Lalitpur', response_rate: 0.65, total_donations: 4 },
    { id: 'D003', name: 'Nisha Tamang',     blood_type: 'B+',  weight: 55, age: 22, last_donation: '2024-11-20', eligible: true, location: 'Bhaktapur', response_rate: 0.90, total_donations: 10 },
    { id: 'D004', name: 'Bikash Rai',       blood_type: 'O-',  weight: 80, age: 35, last_donation: '2025-06-01', eligible: false, location: 'Kathmandu', response_rate: 0.40, total_donations: 2 },
  ],

  notifications: [
    { id: 'N001', donor_id: 'D001', request_id: 'BR002', message: 'Urgent O- blood needed at B&B Hospital, Lalitpur (2.4 km away)', responded: false, donated: false, date: '2025-07-18', urgency: 'Critical' },
    { id: 'N002', donor_id: 'D001', request_id: 'BR003', message: 'B+ blood needed at Teaching Hospital, Bhaktapur (5.1 km away)', responded: true, donated: true, date: '2025-07-20', urgency: 'Routine' },
    { id: 'N003', donor_id: 'D003', request_id: 'BR004', message: 'AB+ blood needed at Grande Hospital, Kathmandu (1.8 km away)', responded: false, donated: false, date: '2025-07-22', urgency: 'High' },
  ],

  appointments: [
    { id: 'A001', donor: 'Kiran Budhathoki', request_id: 'BR003', date: '2025-07-21', time: '10:00', hospital: 'Teaching Hospital', status: 'completed' },
    { id: 'A002', donor: 'Nisha Tamang',     request_id: 'BR004', date: '2025-07-23', time: '14:00', hospital: 'Grande Hospital', status: 'scheduled' },
  ],

  users: [
    { id: 'U001', name: 'John Doe',         role: 'patient', email: 'john@example.com',  blood_type: 'A+', status: 'active' },
    { id: 'U002', name: 'Kiran Budhathoki', role: 'donor',   email: 'kiran@example.com', blood_type: 'O+', status: 'active' },
    { id: 'U003', name: 'Sita Sharma',      role: 'patient', email: 'sita@example.com',  blood_type: 'O-', status: 'active' },
    { id: 'U004', name: 'Aarav Shrestha',   role: 'donor',   email: 'aarav@example.com', blood_type: 'A+', status: 'active' },
  ],

  mlResults: {
    randomForest: { precision_no: 0.76, recall_no: 0.99, f1_no: 0.65, precision_yes: 0.27, recall_yes: 1.00, f1_yes: 0.42, accuracy: 0.58, auc: 0.80 },
    logisticReg:  { precision_no: 0.76, recall_no: 0.99, f1_no: 0.86, precision_yes: 0.50, recall_yes: 0.03, f1_yes: 0.05, accuracy: 0.75, auc: 0.74 },
  },

  getInventoryByType(type) {
    return this.inventory.find(i => i.blood_type === type);
  },

  getUrgencyBadge(urgency) {
    const map = { 'Critical': 'badge-red', 'High': 'badge-yellow', 'Routine': 'badge-green' };
    return map[urgency] || 'badge-gray';
  },

  getStatusBadge(status) {
    const map = {
      'pending': 'badge-yellow', 'approved': 'badge-blue',
      'fulfilled': 'badge-green', 'rejected': 'badge-red',
      'waiting_for_donor': 'badge-red', 'scheduled': 'badge-blue', 'completed': 'badge-green'
    };
    return map[status] || 'badge-gray';
  },

  predictDonorResponse(donor) {
    // Simplified ML simulation based on doc features
    let score = 0;
    score += donor.response_rate * 40;
    score += (donor.total_donations / 15) * 20;
    score += donor.eligible ? 20 : 0;
    score += Math.random() * 20; // variability
    return Math.min(Math.round(score), 98);
  },
};