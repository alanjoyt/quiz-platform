import os
from datetime import datetime, timezone
from flask import Flask, render_template, redirect, url_for, flash, request, jsonify, Response
from flask_sqlalchemy import SQLAlchemy
from flask_login import LoginManager, login_required, current_user

from models import db, User, Question, UserQuizState, UserResponse, QuizConfig
from auth import auth_bp
from admin import admin_bp
import quiz_engine

app = Flask(__name__)
app.secret_key = os.getenv('SECRET_KEY', 'cheranalloor_faces_default_secret_key_129031')

# Database configuration
db_url = os.getenv('DATABASE_URL')
if db_url and db_url.startswith('postgres://'):
    db_url = db_url.replace('postgres://', 'postgresql://', 1)

# Ensure instance folder exists for SQLite
os.makedirs(app.instance_path, exist_ok=True)
app.config['SQLALCHEMY_DATABASE_URI'] = db_url or f"sqlite:///{os.path.join(app.instance_path, 'quiz.db')}"
app.config['SQLALCHEMY_TRACK_MODIFICATIONS'] = False

db.init_app(app)

# Auto-migrate SQLite schema to add missing columns without losing existing database data
with app.app_context():
    # Add password column if missing
    try:
        db.session.execute(db.text("ALTER TABLE user ADD COLUMN password VARCHAR(255)"))
        db.session.commit()
    except Exception:
        db.session.rollback()
        
    # Add browser_fingerprint column if missing
    try:
        db.session.execute(db.text("ALTER TABLE user ADD COLUMN browser_fingerprint VARCHAR(100)"))
        db.session.commit()
    except Exception:
        db.session.rollback()

# Login manager configuration
login_manager = LoginManager()
login_manager.init_app(app)
login_manager.login_view = 'auth.login'
login_manager.login_message_category = 'info'

@login_manager.user_loader
def load_user(user_id):
    return User.query.get(int(user_id))

# Register Blueprints
app.register_blueprint(auth_bp)
app.register_blueprint(admin_bp)

# Context processor to make settings available globally in Jinja
@app.context_processor
def inject_settings():
    settings = quiz_engine.get_quiz_settings()
    total_q = Question.query.count()
    is_dev = str(os.getenv('DEVELOPMENT_MODE', '')).lower() == 'true' or QuizConfig.get_val('development_mode') == 'true'
    return dict(quiz_settings=settings, total_questions=total_q, is_dev=is_dev)

# Custom filters
@app.template_filter('datetimeformat')
def datetimeformat(value, format='%B %d, %Y %I:%M %p'):
    if not value:
        return ""
    if isinstance(value, str):
        try:
            value = datetime.fromisoformat(value)
        except ValueError:
            return value
    return value.strftime(format)

@app.route('/')
def index():
    settings = quiz_engine.get_quiz_settings()
    total_users = User.query.filter_by(role='user').count()
    
    # Check current user's state if authenticated
    user_state = None
    if current_user.is_authenticated:
        user_state = UserQuizState.query.filter_by(user_id=current_user.id).first()

    return render_template('index.html', total_users=total_users, user_state=user_state)

@app.route('/profile')
@login_required
def profile():
    state = UserQuizState.query.filter_by(user_id=current_user.id).first()
    
    # Fetch responses with questions
    responses = db.session.query(UserResponse, Question)\
        .join(Question, UserResponse.question_id == Question.id)\
        .filter(UserResponse.user_id == current_user.id)\
        .order_by(Question.id.asc()).all()
        
    history = []
    for resp, quest in responses:
        history.append({
            'question_text': quest.text,
            'selected': resp.selected_option,
            'correct': quest.correct_option,
            'is_correct': resp.is_correct,
            'points': resp.points_earned,
            'bonus': resp.speed_bonus,
            'time_spent': round(resp.response_time_ms / 1000, 1)
        })
        
    return render_template('profile.html', state=state, history=history)

@app.route('/quiz', methods=['GET', 'POST'])
@login_required
def quiz_view():
    settings = quiz_engine.get_quiz_settings()
    
    if settings['state'] != 'live':
        return render_template('quiz.html', error_state=settings['state'])

    state = quiz_engine.get_or_create_quiz_state(current_user)
    
    if state.is_disqualified:
        return render_template('quiz.html', disqualified=True, reason=state.disqualification_reason)
        
    if state.quiz_completed_at:
        flash('You have already completed the quiz! Here is your final summary.', 'success')
        return redirect(url_for('profile'))

    # Load current question
    question_id = state.current_question_id
    if not question_id:
        # Re-check or complete
        state.quiz_completed_at = datetime.now(timezone.utc)
        db.session.commit()
        flash('Quiz completed successfully.', 'success')
        return redirect(url_for('profile'))

    question = Question.query.get(question_id)
    if not question:
        flash('Active question not found.', 'error')
        return redirect(url_for('index'))

    # Calculate time remaining
    now = datetime.now(timezone.utc)
    started_at = state.current_question_started_at.replace(tzinfo=timezone.utc)
    elapsed = (now - started_at).total_seconds()
    time_limit = settings['time_limit_sec']
    remaining = max(0, int(time_limit - elapsed))

    # Server-side timeout handler:
    # If the user accesses the page and the time has already expired on the server,
    # auto-submit a blank/timeout answer and advance to next.
    if remaining <= 0:
        quiz_engine.submit_answer(current_user, question_id, None)
        return redirect(url_for('quiz_view'))

    if request.method == 'POST':
        selected_option = request.form.get('option') # A, B, C, D
        success, msg = quiz_engine.submit_answer(current_user, question_id, selected_option)
        if success:
            return redirect(url_for('quiz_view'))
        else:
            flash(msg, 'error')
            return redirect(url_for('quiz_view'))

    # Calculate progress metrics
    total_q = Question.query.count()
    answered_q = UserResponse.query.filter_by(user_id=current_user.id).count()
    progress_percent = int((answered_q / total_q) * 100) if total_q > 0 else 0

    return render_template(
        'quiz.html',
        question=question,
        remaining_seconds=remaining,
        answered_count=answered_q,
        progress_percent=progress_percent,
        warnings_count=state.warnings_count
    )

@app.route('/quiz/warning', methods=['POST'])
@login_required
def quiz_warning():
    """Endpoint for AJAX to log a tab-switching warning."""
    disqualified, count = quiz_engine.handle_tab_switch_warning(current_user)
    return jsonify({
        'success': True,
        'disqualified': disqualified,
        'warnings_count': count
    })

@app.route('/leaderboard')
def leaderboard():
    # Fetch leaderboard users
    users = User.query.filter_by(role='user', is_verified=True).all()
    
    board = []
    for u in users:
        state = UserQuizState.query.filter_by(user_id=u.id).first()
        is_disqualified = state.is_disqualified if state else False
        completed_at = state.quiz_completed_at if state else None
        
        if not is_disqualified:
            board.append({
                'id': u.id,
                'username': u.username,
                'score': u.total_score,
                'completed_at': completed_at
            })
            
    # Sort: Score desc, completed_at asc (non-completed users sorted last)
    def sort_key(x):
        comp_time = x['completed_at'].timestamp() if x['completed_at'] else float('inf')
        return (-x['score'], comp_time)
        
    board.sort(key=sort_key)
    
    # Render template
    return render_template('leaderboard.html', leaderboard=board)

# SEO Routes
@app.route('/robots.txt')
def robots():
    content = """User-agent: *
Allow: /
Disallow: /admin/
Disallow: /quiz/
Disallow: /profile
Sitemap: {0}sitemap.xml""".format(request.url_root)
    return Response(content, mimetype='text/plain')

@app.route('/sitemap.xml')
def sitemap():
    pages = []
    # Add public static pages
    pages.append((request.url_root, '1.0'))
    pages.append((request.url_root + 'leaderboard', '0.8'))
    pages.append((request.url_root + 'login', '0.7'))
    
    xml = ['<?xml version="1.0" encoding="UTF-8"?>']
    xml.append('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">')
    
    for url, pri in pages:
        xml.append('  <url>')
        xml.append(f'    <loc>{url}</loc>')
        xml.append(f'    <priority>{pri}</priority>')
        xml.append('    <changefreq>daily</changefreq>')
        xml.append('  </url>')
        
    xml.append('</urlset>')
    return Response(''.join(xml), mimetype='application/xml')

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)
