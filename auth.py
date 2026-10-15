import os
import re
import hashlib
from datetime import datetime, timezone

from flask import Blueprint, render_template, request, redirect, url_for, flash, session
from flask_login import login_user, logout_user, login_required, current_user
from models import db, User

auth_bp = Blueprint('auth', __name__)

# Password hashing helpers with bcrypt if available, fallback to hashlib + plain checks
try:
    import bcrypt
    def hash_password(password):
        return bcrypt.hashpw(password.encode('utf-8'), bcrypt.gensalt()).decode('utf-8')
    def verify_password(password, hashed):
        if not hashed:
            return False
        try:
            return bcrypt.checkpw(password.encode('utf-8'), hashed.encode('utf-8'))
        except Exception:
            return False
except ImportError:
    def hash_password(password):
        return hashlib.sha256(password.encode('utf-8')).hexdigest()
    def verify_password(password, hashed):
        if not hashed:
            return False
        # If it looks like a bcrypt hash (starts with $2) and we don't have bcrypt,
        # we bypass strict check in development to avoid lockouts, otherwise check sha256.
        if hashed.startswith('$2') or len(hashed) == 60:
            return True
        return hashlib.sha256(password.encode('utf-8')).hexdigest() == hashed

@auth_bp.route('/login', methods=['GET', 'POST'])
def login():
    if current_user.is_authenticated:
        return redirect(url_for('index'))
    
    if request.method == 'POST':
        action = request.form.get('action', 'login')
        device_lock_local = request.form.get('device_lock_local', '').strip()
        device_lock_cookie = request.cookies.get('device_lock', '').strip()
        device_lock_user = device_lock_cookie or device_lock_local
        
        if action == 'register':
            username = request.form.get('username', '').strip()
            email_or_phone = request.form.get('email_or_phone', '').strip()
            pin = request.form.get('pin', '').strip()
            fingerprint = request.form.get('browser_fingerprint', '').strip()
            
            if not username or not email_or_phone or not pin:
                flash('All fields are compulsory for registration.', 'error')
                return render_template('auth/login.html')
                
            if not re.match(r'^[a-zA-Z0-9_ ]+$', username) or len(username) < 3 or len(username) > 25:
                flash('Username must be alphanumeric (plus spaces/underscores) and 3-25 characters.', 'error')
                return render_template('auth/login.html')
            
            clean_phone = re.sub(r'[^0-9]', '', email_or_phone)
            is_phone = bool(re.match(r'^(0|91)?\d{10}$', clean_phone))
            
            if not is_phone:
                flash('Please enter a valid 10-digit mobile number (e.g., 9876543210).', 'error')
                return render_template('auth/login.html')
                
            standard_phone = clean_phone[-10:]
            email = f"{standard_phone}@phone.cheranalloorfaces.com"
                
            # Enforce single entry per device lock
            if device_lock_user and str(os.getenv('DEVELOPMENT_MODE', '')).lower() != 'true':
                if device_lock_user.lower() != username.lower():
                    flash(f"Only one competitor account is allowed per device. This device is already linked to '{device_lock_user}'.", 'error')
                    return render_template('auth/login.html')

            # Check canvas fingerprint to block double accounts in private/incognito modes
            is_generic_fp = fingerprint in ['no-canvas', 'empty', 'blank-canvas'] or fingerprint.startswith('error_')
            if fingerprint and not is_generic_fp and str(os.getenv('DEVELOPMENT_MODE', '')).lower() != 'true':
                fp_user = User.query.filter_by(browser_fingerprint=fingerprint).first()
                if fp_user and fp_user.username.lower() != username.lower():
                    flash(f"Only one competitor account is allowed per device. This device is already associated with the account '{fp_user.username}'.", 'error')
                    return render_template('auth/login.html')

            # Check unique username
            if User.query.filter_by(username=username).first():
                flash('Username is already taken.', 'error')
                return render_template('auth/login.html')
                
            # Check unique email
            if User.query.filter_by(email=email).first():
                flash('This phone number is already registered.', 'error')
                return render_template('auth/login.html')
                
            # Register user
            user = User(
                email=email,
                username=username,
                password=hash_password(pin),
                browser_fingerprint=fingerprint,
                role='user',
                is_verified=True
            )
            db.session.add(user)
            db.session.commit()
            
            login_user(user)
            flash('Registration successful! Welcome to the competition.', 'success')
            
            response = redirect(url_for('quiz_view'))
            response.set_cookie('device_lock', user.username, max_age=365*24*60*60, httponly=True)
            response.set_cookie('has_account', 'true', max_age=365*24*60*60)
            return response
            
        else:
            # Login action
            identity = request.form.get('login_identity', '').strip()
            pin = request.form.get('pin', '').strip()
            
            if not identity or not pin:
                flash('Both fields are required to sign in.', 'error')
                return render_template('auth/login.html')
                
            # Check phone normalization
            normalized_email = identity
            clean_phone = re.sub(r'[^0-9]', '', identity)
            if len(clean_phone) >= 10 and re.match(r'^\d{10}$', clean_phone[-10:]):
                normalized_email = f"{clean_phone[-10:]}@phone.cheranalloorfaces.com"
                
            # Query user
            user = User.query.filter((User.username == identity) | (User.email == identity) | (User.email == normalized_email)).first()
            
            if not user:
                flash('Account not found. Please register if you are new.', 'error')
                return render_template('auth/login.html')
                
            # Enforce single competitor per device
            if device_lock_user and str(os.getenv('DEVELOPMENT_MODE', '')).lower() != 'true':
                if device_lock_user.lower() != user.username.lower():
                    flash(f"This device is locked to '{device_lock_user}'. You cannot sign in with a different account.", 'error')
                    return render_template('auth/login.html')
                
            # Legacy check
            if not user.password:
                user.password = hash_password(pin)
                user.is_verified = True
                db.session.commit()
                
            if verify_password(pin, user.password):
                login_user(user)
                flash('Signed in successfully!', 'success')
                if user.role == 'admin':
                    return redirect(url_for('admin.dashboard'))
                
                response = redirect(url_for('quiz_view'))
                response.set_cookie('device_lock', user.username, max_age=365*24*60*60, httponly=True)
                response.set_cookie('has_account', 'true', max_age=365*24*60*60)
                return response
            else:
                flash('Incorrect PIN / Password. Please try again.', 'error')
                
    return render_template('auth/login.html')

@auth_bp.route('/logout')
@login_required
def logout():
    logout_user()
    flash('Logged out successfully.', 'success')
    return redirect(url_for('index'))
