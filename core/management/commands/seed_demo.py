from django.core.management.base import BaseCommand
from django.contrib.auth.models import User
from core.models import UserProfile, BloodRequest, DonorNotification, DonationAppointment, BloodInventoryUnit
from django.utils import timezone
from datetime import date, timedelta

class Command(BaseCommand):
    help = 'Seed demo data (safe reset except superusers).'

    def handle(self, *args, **kwargs):
        DonationAppointment.objects.all().delete()
        DonorNotification.objects.all().delete()
        BloodRequest.objects.all().delete()
        BloodInventoryUnit.objects.all().delete()
        for p in UserProfile.objects.exclude(user__is_superuser=True):
            p.user.delete()

        donors = []
        for i in range(1, 9):
            u = User.objects.create_user(f'donor{i}', password='Donor@12345')
            donors.append(UserProfile.objects.create(user=u, role='donor', phone=f'98000000{i:02d}', blood_group='A+' if i%2 else 'O+', is_verified=True, latitude=27.7+i*0.001, longitude=85.3+i*0.001))
        for i in range(1, 13):
            u = User.objects.create_user(f'patient{i}', password='Patient@12345')
            UserProfile.objects.create(user=u, role='patient', phone=f'98100000{i:02d}', blood_group='B+' if i%2 else 'A+', is_verified=True, latitude=27.71+i*0.001, longitude=85.31+i*0.001)

        first_patient = UserProfile.objects.filter(role='patient').first()
        req = BloodRequest.objects.create(patient=first_patient,blood_group='A+',units_needed=2,urgency='critical',hospital_name='Bir Hospital',hospital_address='Kathmandu',latitude=27.7172,longitude=85.3240,status='pending')
        DonorNotification.objects.create(request=req, donor=donors[0], response_probability=0.8)
        for bg in ['A+','A-','B+','B-','AB+','AB-','O+','O-']:
            BloodInventoryUnit.objects.create(blood_group=bg, status='available', expires_on=date.today()+timedelta(days=30))
        self.stdout.write(self.style.SUCCESS('Demo seed complete'))
