from rest_framework import serializers
from django.contrib.auth.models import User
from .models import UserProfile, BloodRequest, DonorNotification, BloodInventoryUnit, BloodIssuance, DonationAppointment

class UserSerializer(serializers.ModelSerializer):
    class Meta:
        model = User
        fields = ['id', 'username', 'email']

class UserProfileSerializer(serializers.ModelSerializer):
    user = UserSerializer(read_only=True)

    class Meta:
        model = UserProfile
        fields = '__all__'

class BloodRequestSerializer(serializers.ModelSerializer):
    class Meta:
        model = BloodRequest
        fields = '__all__'
        read_only_fields = ['patient', 'status', 'units_fulfilled', 'created_at']

class DonorNotificationSerializer(serializers.ModelSerializer):
    class Meta:
        model = DonorNotification
        fields = '__all__'

class BloodInventoryUnitSerializer(serializers.ModelSerializer):
    class Meta:
        model = BloodInventoryUnit
        fields = '__all__'

class BloodIssuanceSerializer(serializers.ModelSerializer):
    class Meta:
        model = BloodIssuance
        fields = '__all__'

class DonationAppointmentSerializer(serializers.ModelSerializer):
    class Meta:
        model = DonationAppointment
        fields = '__all__'
