from django.core.management.base import BaseCommand
from core.models import DonationAppointment, DonorNotification, BloodRequest, BloodInventoryUnit

class Command(BaseCommand):
    help = 'Clear transactional/demo data only (keep users).'

    def handle(self, *args, **kwargs):
        DonationAppointment.objects.all().delete()
        DonorNotification.objects.all().delete()
        BloodRequest.objects.all().delete()
        BloodInventoryUnit.objects.all().delete()
        self.stdout.write(self.style.SUCCESS('Production reset complete (users kept).'))
