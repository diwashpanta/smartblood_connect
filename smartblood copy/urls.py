from django.contrib import admin
from django.urls import path, include
from core.views import home_view
from django.conf import settings
from django.conf.urls.static import static
urlpatterns=[path('admin/',admin.site.urls),path('',home_view,name='home'),path('app/',include('core.urls'))]
if settings.DEBUG:
    urlpatterns += static(settings.MEDIA_URL, document_root=settings.MEDIA_ROOT)
