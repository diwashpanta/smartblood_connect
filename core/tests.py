from django.test import TestCase
from django.contrib.auth.models import User
from rest_framework.test import APIClient
from .models import UserProfile, BloodRequest

class RolePermissionTests(TestCase):
    def setUp(self):
        self.patient_user = User.objects.create_user('patient', password='Pass12345')
        self.patient_profile = UserProfile.objects.create(user=self.patient_user, role='patient', phone='1', is_verified=True)
        self.donor_user = User.objects.create_user('donor', password='Pass12345')
        self.donor_profile = UserProfile.objects.create(user=self.donor_user, role='donor', phone='2', is_verified=True)
        self.admin_user = User.objects.create_user('adminx', password='Pass12345')
        self.admin_profile = UserProfile.objects.create(user=self.admin_user, role='admin', phone='3', is_verified=True)

    def test_patient_create_request(self):
        c = APIClient(); c.force_authenticate(user=self.patient_user)
        resp = c.post('/app/api/requests/', {'blood_group':'A+','units_needed':1,'urgency':'urgent','hospital_name':'H','hospital_address':'Addr','latitude':1.0,'longitude':2.0}, format='json')
        self.assertIn(resp.status_code, [200,201])

    def test_donor_cannot_create_inventory(self):
        c = APIClient(); c.force_authenticate(user=self.donor_user)
        resp = c.post('/app/api/inventory/', {'blood_group':'A+','status':'available','expires_on':'2030-01-01'}, format='json')
        self.assertEqual(resp.status_code, 403)

    def test_admin_can_approve_request(self):
        req = BloodRequest.objects.create(patient=self.patient_profile,blood_group='A+',units_needed=1,urgency='urgent',hospital_name='H',hospital_address='A',latitude=1,longitude=2,status='pending')
        c = APIClient(); c.force_authenticate(user=self.admin_user)
        resp = c.post(f'/app/api/requests/{req.id}/approve/')
        self.assertEqual(resp.status_code, 200)

    def test_match_donors_endpoint(self):
        req = BloodRequest.objects.create(patient=self.patient_profile,blood_group='A+',units_needed=1,urgency='urgent',hospital_name='H',hospital_address='A',latitude=1,longitude=2,status='approved')
        self.donor_profile.blood_group='A+'; self.donor_profile.latitude=1.1; self.donor_profile.longitude=2.1; self.donor_profile.save()
        c = APIClient(); c.force_authenticate(user=self.admin_user)
        resp = c.post(f'/app/api/requests/{req.id}/match_donors/')
        self.assertEqual(resp.status_code, 200)

    def test_dashboard_pages(self):
        c = self.client
        c.login(username='patient', password='Pass12345')
        self.assertEqual(c.get('/app/patient/dashboard/').status_code, 200)
        c.logout()
        c.login(username='donor', password='Pass12345')
        self.assertEqual(c.get('/app/donor/dashboard/').status_code, 200)
        c.logout()
        c.login(username='adminx', password='Pass12345')
        self.assertEqual(c.get('/app/adminpanel/dashboard/').status_code, 200)
