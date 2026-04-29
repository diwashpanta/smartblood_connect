from django.contrib import admin
from .models import *
for m in [UserProfile,BloodRequest,DonorNotification,DonationAppointment,BloodInventoryUnit,BloodIssuance,InventoryTransaction]:
 admin.site.register(m)
