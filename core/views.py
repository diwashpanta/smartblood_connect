from django.shortcuts import render, redirect
from django.contrib.auth import authenticate, login, logout
from django.contrib.auth.decorators import login_required
from django.contrib.auth.models import User
from django.contrib import messages
from django.http import JsonResponse
from django.views.decorators.csrf import ensure_csrf_cookie
from rest_framework import viewsets, status
from rest_framework.decorators import action
from rest_framework.response import Response
from rest_framework.permissions import IsAuthenticated
from rest_framework_simplejwt.views import TokenObtainPairView, TokenRefreshView

from .models import UserProfile, BloodRequest, DonorNotification, BloodInventoryUnit, BloodIssuance, DonationAppointment
from .serializers import UserProfileSerializer, BloodRequestSerializer, DonorNotificationSerializer, BloodInventoryUnitSerializer, BloodIssuanceSerializer, DonationAppointmentSerializer
from .permissions import IsAdminRole
from .services import donor_candidates_for_request


def home_view(request):
    if request.user.is_authenticated:
        return redirect('role_dashboard')
    return redirect('login')


def register_view(request):
    if request.method == 'POST':
        username = request.POST['username'].strip()
        password = request.POST['password']
        role = request.POST['role']
        if User.objects.filter(username=username).exists():
            messages.error(request, 'Username already exists.')
            return render(request, 'auth/register.html', {'default_role': request.GET.get('role', 'patient')})
        user = User.objects.create_user(username=username, password=password)
        UserProfile.objects.create(
            user=user,
            role=role,
            phone=request.POST.get('phone', ''),
            blood_group=request.POST.get('blood_group', ''),
            is_verified=(role == 'patient'),
            latitude=float(request.POST.get('latitude') or 0) if request.POST.get('latitude') else None,
            longitude=float(request.POST.get('longitude') or 0) if request.POST.get('longitude') else None,
        )
        messages.success(request, 'Registration complete. Please login.')
        return redirect('login')
    return render(request, 'auth/register.html', {'default_role': request.GET.get('role', 'patient')})


def login_view(request):
    if request.method == 'POST':
        user = authenticate(request, username=request.POST.get('username'), password=request.POST.get('password'))
        if user:
            login(request, user)
            return redirect('role_dashboard')
        messages.error(request, 'Invalid credentials')
    return render(request, 'auth/login.html')


def logout_view(request):
    logout(request)
    return redirect('home')


@login_required
@ensure_csrf_cookie
def role_dashboard(request):
    return render(request, 'dashboard.html')


@login_required
def dashboard_data(request):
    profile = request.user.userprofile
    requests_qs = BloodRequest.objects.all().order_by('-created_at')
    if profile.role == 'patient':
        requests_qs = requests_qs.filter(patient=profile)

    notifications_qs = DonorNotification.objects.select_related('request', 'donor').order_by('-created_at')
    if profile.role == 'donor':
        notifications_qs = notifications_qs.filter(donor=profile)

    inventory_qs = BloodInventoryUnit.objects.order_by('-id')
    users_qs = UserProfile.objects.select_related('user').order_by('id')

    payload = {
        'role': profile.role,
        'current_user': {
            'username': request.user.username,
            'role': profile.role,
            'blood_group': profile.blood_group,
        },
        'requests': [
            {
                'id': r.id,
                'patient': r.patient.user.username,
                'blood_group': r.blood_group,
                'units_needed': r.units_needed,
                'units_fulfilled': r.units_fulfilled,
                'urgency': r.urgency,
                'hospital_name': r.hospital_name,
                'hospital_address': r.hospital_address,
                'status': r.status,
                'created_at': r.created_at.isoformat(),
            }
            for r in requests_qs
        ],
        'notifications': [
            {
                'id': n.id,
                'request_id': n.request_id,
                'donor': n.donor.user.username,
                'response_probability': n.response_probability,
                'responded': n.responded,
                'willing': n.willing,
                'created_at': n.created_at.isoformat(),
            }
            for n in notifications_qs
        ],
        'inventory': [
            {
                'id': i.id,
                'blood_group': i.blood_group,
                'status': i.status,
                'expires_on': i.expires_on.isoformat(),
            }
            for i in inventory_qs
        ],
        'users': [
            {
                'id': u.id,
                'username': u.user.username,
                'role': u.role,
                'blood_group': u.blood_group,
                'phone': u.phone,
            }
            for u in users_qs
        ],
        'counts': {
            'requests': requests_qs.count(),
            'pending': requests_qs.filter(status='pending').count(),
            'fulfilled': requests_qs.filter(status='fulfilled').count(),
            'inventory_available': inventory_qs.filter(status='available').count(),
            'users': users_qs.count(),
        },
    }
    return JsonResponse(payload)


@login_required
def patient_request_create(request):
    if request.user.userprofile.role != 'patient':
        return redirect('role_dashboard')
    if request.method == 'POST':
        BloodRequest.objects.create(
            patient=request.user.userprofile,
            blood_group=request.POST['blood_group'],
            units_needed=int(request.POST['units_needed']),
            urgency=request.POST['urgency'],
            hospital_name=request.POST['hospital_name'],
            hospital_address=request.POST['hospital_address'],
            latitude=float(request.POST['latitude']),
            longitude=float(request.POST['longitude']),
            status='pending',
        )
        return redirect('role_dashboard')
    return render(request, 'patient/request_create.html')


class UserProfileViewSet(viewsets.ReadOnlyModelViewSet):
    queryset = UserProfile.objects.select_related('user').all()
    serializer_class = UserProfileSerializer
    permission_classes = [IsAuthenticated]


class BloodRequestViewSet(viewsets.ModelViewSet):
    queryset = BloodRequest.objects.all().order_by('-created_at')
    serializer_class = BloodRequestSerializer
    permission_classes = [IsAuthenticated]

    def get_queryset(self):
        user = self.request.user
        if user.userprofile.role == 'patient':
            return BloodRequest.objects.filter(patient=user.userprofile).order_by('-created_at')
        return super().get_queryset()

    def perform_create(self, serializer):
        if self.request.user.userprofile.role != 'patient':
            raise PermissionError('Only patients can create blood requests')
        serializer.save(patient=self.request.user.userprofile, status='pending')

    @action(detail=True, methods=['post'], permission_classes=[IsAuthenticated, IsAdminRole])
    def approve(self, request, pk=None):
        obj = self.get_object()
        obj.status = 'approved'
        obj.save(update_fields=['status'])
        ranked = donor_candidates_for_request(obj)
        created = 0
        for donor, _dist, score in ranked:
            _, made = DonorNotification.objects.get_or_create(
                request=obj,
                donor=donor,
                defaults={'response_probability': score, 'responded': False, 'willing': False},
            )
            if made:
                created += 1
        return Response({'status': 'approved', 'notifications_created': created})

    @action(detail=True, methods=['post'], permission_classes=[IsAuthenticated, IsAdminRole])
    def match_donors(self, request, pk=None):
        req = self.get_object()
        ranked = donor_candidates_for_request(req)
        created = 0
        for donor, _dist, score in ranked:
            DonorNotification.objects.get_or_create(
                request=req,
                donor=donor,
                defaults={
                    'response_probability': score,
                    'responded': False,
                    'willing': False,
                },
            )
            created += 1
        return Response({'matched_notifications': created})


class DonorNotificationViewSet(viewsets.ModelViewSet):
    queryset = DonorNotification.objects.select_related('request', 'donor').all().order_by('-created_at')
    serializer_class = DonorNotificationSerializer
    permission_classes = [IsAuthenticated]

    def get_queryset(self):
        user = self.request.user
        if user.userprofile.role == 'donor':
            return self.queryset.filter(donor=user.userprofile)
        return self.queryset

    @action(detail=True, methods=['post'], permission_classes=[IsAuthenticated])
    def respond(self, request, pk=None):
        note = self.get_object()
        if request.user.userprofile.role != 'donor' or note.donor_id != request.user.userprofile.id:
            return Response({'detail': 'Forbidden'}, status=status.HTTP_403_FORBIDDEN)
        willing = bool(request.data.get('willing'))
        note.responded = True
        note.willing = willing
        note.save(update_fields=['responded', 'willing'])
        if willing:
            DonationAppointment.objects.get_or_create(
                request=note.request,
                donor=note.donor,
                defaults={
                    'scheduled_at': note.request.created_at,
                    'location': note.request.hospital_address,
                },
            )
        return Response({'responded': True, 'willing': willing})


class BloodInventoryUnitViewSet(viewsets.ModelViewSet):
    queryset = BloodInventoryUnit.objects.all().order_by('-id')
    serializer_class = BloodInventoryUnitSerializer
    permission_classes = [IsAuthenticated, IsAdminRole]


class BloodIssuanceViewSet(viewsets.ModelViewSet):
    queryset = BloodIssuance.objects.select_related('request', 'patient', 'unit').all().order_by('-issued_at')
    serializer_class = BloodIssuanceSerializer
    permission_classes = [IsAuthenticated, IsAdminRole]


class DonationAppointmentViewSet(viewsets.ModelViewSet):
    queryset = DonationAppointment.objects.select_related('request', 'donor').all().order_by('-scheduled_at')
    serializer_class = DonationAppointmentSerializer
    permission_classes = [IsAuthenticated]

    def get_queryset(self):
        user = self.request.user
        if user.userprofile.role == 'donor':
            return self.queryset.filter(donor=user.userprofile)
        return self.queryset


JWTTokenObtainPairView = TokenObtainPairView
JWTTokenRefreshView = TokenRefreshView

