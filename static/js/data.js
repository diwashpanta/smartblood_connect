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

  requests: [],

  donors: [],

  notifications: [],

  appointments: [],

  users: [],

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
