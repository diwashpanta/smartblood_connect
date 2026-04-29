from django.db import models
from django.contrib.auth.models import User
ROLE=[('patient','Patient'),('donor','Donor'),('admin','Admin')]
BLOOD=[('A+','A+'),('A-','A-'),('B+','B+'),('B-','B-'),('AB+','AB+'),('AB-','AB-'),('O+','O+'),('O-','O-')]

class UserProfile(models.Model):
 user=models.OneToOneField(User,on_delete=models.CASCADE)
 role=models.CharField(max_length=10,choices=ROLE)
 phone=models.CharField(max_length=20)
 blood_group=models.CharField(max_length=3,choices=BLOOD,blank=True)
 is_verified=models.BooleanField(default=False)
 latitude=models.FloatField(null=True,blank=True)
 longitude=models.FloatField(null=True,blank=True)

class BloodRequest(models.Model):
 patient=models.ForeignKey(UserProfile,on_delete=models.CASCADE,related_name='requests')
 blood_group=models.CharField(max_length=3,choices=BLOOD)
 units_needed=models.PositiveIntegerField()
 units_fulfilled=models.PositiveIntegerField(default=0)
 urgency=models.CharField(max_length=20,default='urgent')
 hospital_name=models.CharField(max_length=120)
 hospital_address=models.CharField(max_length=255)
 latitude=models.FloatField(); longitude=models.FloatField()
 status=models.CharField(max_length=30,default='pending')
 created_at=models.DateTimeField(auto_now_add=True)

class DonorNotification(models.Model):
 request=models.ForeignKey(BloodRequest,on_delete=models.CASCADE,related_name='notifications')
 donor=models.ForeignKey(UserProfile,on_delete=models.CASCADE,related_name='notifications')
 response_probability=models.FloatField(default=0)
 responded=models.BooleanField(default=False)
 willing=models.BooleanField(default=False)
 created_at=models.DateTimeField(auto_now_add=True)

class DonationAppointment(models.Model):
 request=models.ForeignKey(BloodRequest,on_delete=models.CASCADE)
 donor=models.ForeignKey(UserProfile,on_delete=models.CASCADE)
 scheduled_at=models.DateTimeField()
 location=models.CharField(max_length=255)
 completed=models.BooleanField(default=False)

class BloodInventoryUnit(models.Model):
 donor=models.ForeignKey(UserProfile,on_delete=models.SET_NULL,null=True,blank=True)
 blood_group=models.CharField(max_length=3,choices=BLOOD)
 status=models.CharField(max_length=20,default='available')
 collected_on=models.DateField(auto_now_add=True)
 expires_on=models.DateField()

class BloodIssuance(models.Model):
 request=models.ForeignKey(BloodRequest,on_delete=models.CASCADE)
 patient=models.ForeignKey(UserProfile,on_delete=models.CASCADE)
 unit=models.OneToOneField(BloodInventoryUnit,on_delete=models.CASCADE)
 issued_at=models.DateTimeField(auto_now_add=True)
 issued_by=models.ForeignKey(UserProfile,on_delete=models.SET_NULL,null=True,related_name='issued_records')

class InventoryTransaction(models.Model):
 unit=models.ForeignKey(BloodInventoryUnit,on_delete=models.CASCADE)
 action=models.CharField(max_length=50)
 actor=models.ForeignKey(UserProfile,on_delete=models.SET_NULL,null=True)
 created_at=models.DateTimeField(auto_now_add=True)
 detail=models.CharField(max_length=255,blank=True)
