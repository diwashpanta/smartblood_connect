from django.contrib import admin
from django.urls import path, include
from core.views import home_view
urlpatterns=[path('admin/',admin.site.urls),path('',home_view,name='home'),path('app/',include('core.urls'))]
