from django.urls import path, include
from rest_framework.routers import DefaultRouter
from .views import register_view, login_view, logout_view, role_dashboard, dashboard_data, patient_request_create, UserProfileViewSet, BloodRequestViewSet, DonorNotificationViewSet, BloodInventoryUnitViewSet, BloodIssuanceViewSet, DonationAppointmentViewSet, JWTTokenObtainPairView, JWTTokenRefreshView

router = DefaultRouter()
router.register('profiles', UserProfileViewSet, basename='profiles')
router.register('requests', BloodRequestViewSet, basename='requests')
router.register('notifications', DonorNotificationViewSet, basename='notifications')
router.register('inventory', BloodInventoryUnitViewSet, basename='inventory')
router.register('issuances', BloodIssuanceViewSet, basename='issuances')
router.register('appointments', DonationAppointmentViewSet, basename='appointments')

urlpatterns = [
    path('register/', register_view, name='register'),
    path('login/', login_view, name='login'),
    path('logout/', logout_view, name='logout'),
    path('dashboard/', role_dashboard, name='role_dashboard'),
    path('dashboard/data/', dashboard_data, name='dashboard_data'),
    path('patient/dashboard/', role_dashboard, name='patient_dashboard'),
    path('donor/dashboard/', role_dashboard, name='donor_dashboard'),
    path('adminpanel/dashboard/', role_dashboard, name='admin_dashboard'),
    path('patient/requests/new/', patient_request_create, name='patient_request_create'),
    path('api/token/', JWTTokenObtainPairView.as_view(), name='token_obtain_pair'),
    path('api/token/refresh/', JWTTokenRefreshView.as_view(), name='token_refresh'),
    path('api/', include(router.urls)),
]
