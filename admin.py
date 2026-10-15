import csv
from io import StringIO
from datetime import datetime, timezone, timedelta
IST = timezone(timedelta(hours=5, minutes=30))
from flask import Blueprint, render_template, request, redirect, url_for, flash, Response
from flask_login import login_required, current_user

from models import db, User, Question, QuizConfig, UserQuizState, UserResponse
import quiz_engine

admin_bp = Blueprint('admin', __name__, url_prefix='/admin')

@admin_bp.before_request
@login_required
def require_admin():
    if current_user.role != 'admin':
        flash('Access denied. Administrator privileges required.', 'error')
        return redirect(url_for('index'))

@admin_bp.route('/dashboard', methods=['GET', 'POST'])
def dashboard():
    if request.method == 'POST':
        # Update settings
        state = request.form.get('quiz_state', 'inactive')
        start_time_str = request.form.get('quiz_start_time', '').strip()
        duration_mins = request.form.get('quiz_duration_mins', '30').strip()
        time_limit_sec = request.form.get('question_time_limit', '30').strip()

        QuizConfig.set_val('quiz_state', state)
        QuizConfig.set_val('quiz_start_time', start_time_str)
        QuizConfig.set_val('quiz_duration_mins', duration_mins)
        QuizConfig.set_val('question_time_limit', time_limit_sec)
        
        flash('Quiz configurations updated successfully.', 'success')
        return redirect(url_for('admin.dashboard'))

    # Load statistics
    total_users = User.query.count()
    verified_users = User.query.filter_by(is_verified=True).count()
    active_takers = UserQuizState.query.filter(UserQuizState.quiz_completed_at.is_(None), UserQuizState.is_disqualified == False).count()
    completed_takers = UserQuizState.query.filter(UserQuizState.quiz_completed_at.isnot(None), UserQuizState.is_disqualified == False).count()
    disqualified_takers = UserQuizState.query.filter_by(is_disqualified=True).count()
    questions_count = Question.query.count()

    # Load settings
    settings = quiz_engine.get_quiz_settings()
    start_time_val = QuizConfig.get_val('quiz_start_time', '')

    return render_template(
        'admin/dashboard.html',
        total_users=total_users,
        verified_users=verified_users,
        active_takers=active_takers,
        completed_takers=completed_takers,
        disqualified_takers=disqualified_takers,
        questions_count=questions_count,
        settings=settings,
        start_time_val=start_time_val
    )

@admin_bp.route('/questions', methods=['GET', 'POST'])
def questions():
    if request.method == 'POST':
        action = request.form.get('action')
        
        if action == 'add':
            text = request.form.get('text', '').strip()
            option_a = request.form.get('option_a', '').strip()
            option_b = request.form.get('option_b', '').strip()
            option_c = request.form.get('option_c', '').strip()
            option_d = request.form.get('option_d', '').strip()
            correct_option = request.form.get('correct_option', 'A').strip().upper()
            points = int(request.form.get('points', 10))

            if not all([text, option_a, option_b, option_c, option_d, correct_option]):
                flash('All fields are required to add a question.', 'error')
            else:
                q = Question(
                    text=text,
                    option_a=option_a,
                    option_b=option_b,
                    option_c=option_c,
                    option_d=option_d,
                    correct_option=correct_option,
                    points=points
                )
                db.session.add(q)
                db.session.commit()
                flash('Question added successfully.', 'success')
                
        elif action == 'edit':
            q_id = int(request.form.get('id', 0))
            q = Question.query.get(q_id)
            if q:
                q.text = request.form.get('text', '').strip()
                q.option_a = request.form.get('option_a', '').strip()
                q.option_b = request.form.get('option_b', '').strip()
                q.option_c = request.form.get('option_c', '').strip()
                q.option_d = request.form.get('option_d', '').strip()
                q.correct_option = request.form.get('correct_option', 'A').strip().upper()
                q.points = int(request.form.get('points', 10))
                db.session.commit()
                flash('Question updated successfully.', 'success')
            else:
                flash('Question not found.', 'error')
                
        elif action == 'delete':
            q_id = int(request.form.get('id', 0))
            q = Question.query.get(q_id)
            if q:
                db.session.delete(q)
                db.session.commit()
                flash('Question deleted successfully.', 'success')
            else:
                flash('Question not found.', 'error')
                
        return redirect(url_for('admin.questions'))

    all_questions = Question.query.order_by(Question.id.asc()).all()
    return render_template('admin/questions.html', questions=all_questions)

@admin_bp.route('/users')
def users():
    search = request.args.get('search', '').strip()
    
    query = User.query
    if search:
        query = query.filter(
            (User.username.ilike(f'%{search}%')) | 
            (User.email.ilike(f'%{search}%'))
        )
        
    all_users = query.all()
    
    # Enrich user objects with their quiz state details for display
    users_data = []
    for u in all_users:
        state = UserQuizState.query.filter_by(user_id=u.id).first()
        status = 'Not Started'
        warnings = 0
        disqualified = False
        reason = ''
        
        if state:
            warnings = state.warnings_count
            if state.is_disqualified:
                status = 'Disqualified'
                disqualified = True
                reason = state.disqualification_reason or 'No reason provided'
            elif state.quiz_completed_at:
                completed_at_ist = state.quiz_completed_at.replace(tzinfo=timezone.utc).astimezone(IST)
                status = f"Completed ({completed_at_ist.strftime('%Y-%m-%d %H:%M:%S')})"
            else:
                status = 'In Progress'
        
        email_val = u.email
        is_phone = '@phone.cheranalloorfaces.com' in email_val
        if is_phone:
            contact_display = email_val.replace('@phone.cheranalloorfaces.com', '')
        else:
            contact_display = email_val

        users_data.append({
            'user': u,
            'status': status,
            'warnings': warnings,
            'disqualified': disqualified,
            'reason': reason,
            'score': u.total_score,
            'contact': contact_display,
            'is_phone': is_phone
        })
        
    return render_template('admin/users.html', users=users_data, search=search)

@admin_bp.route('/users/disqualify/<int:user_id>', methods=['POST'])
def disqualify_user(user_id):
    reason = request.form.get('reason', 'Disqualified by administrator.').strip()
    quiz_engine.disqualify_user_manually(user_id, reason)
    flash(f'User was disqualified.', 'warning')
    return redirect(url_for('admin.users'))

@admin_bp.route('/users/reset/<int:user_id>', methods=['POST'])
def reset_user(user_id):
    quiz_engine.reset_user_progress(user_id)
    flash(f'User quiz progress was reset.', 'success')
    return redirect(url_for('admin.users'))

@admin_bp.route('/export')
def export_leaderboard():
    # Fetch all verified users and calculate ranks
    users = User.query.filter_by(role='user', is_verified=True).all()
    
    # Calculate ranking list
    leaderboard = []
    for u in users:
        state = UserQuizState.query.filter_by(user_id=u.id).first()
        is_disqualified = state.is_disqualified if state else False
        completed_at = state.quiz_completed_at if state else None
        
        # Don't put disqualified users on leaderboard
        if not is_disqualified:
            leaderboard.append({
                'username': u.username,
                'email': u.email,
                'score': u.total_score,
                'base_score': u.base_score,
                'speed_bonus': u.speed_bonus_score,
                'completed_at': completed_at
            })
            
    # Sort leaderboard: Score descending, completed_at ascending
    # Non-completed users have completed_at=None, we place them at the end.
    def sort_key(x):
        comp_time = x['completed_at'].timestamp() if x['completed_at'] else float('inf')
        return (-x['score'], comp_time)
        
    leaderboard.sort(key=sort_key)
    
    # Create CSV response
    si = StringIO()
    cw = csv.writer(si)
    cw.writerow(['Rank', 'Username', 'Email/Phone', 'Total Score', 'Base Score', 'Speed Bonus', 'Completion Time'])
    
    for rank, entry in enumerate(leaderboard, 1):
        if entry['completed_at']:
            completed_at_ist = entry['completed_at'].replace(tzinfo=timezone.utc).astimezone(IST)
            comp_str = completed_at_ist.strftime('%Y-%m-%d %H:%M:%S')
        else:
            comp_str = 'Not Completed'
        contact = entry['email']
        if '@phone.cheranalloorfaces.com' in contact:
            contact = contact.replace('@phone.cheranalloorfaces.com', '')
        cw.writerow([
            rank,
            entry['username'],
            contact,
            entry['score'],
            entry['base_score'],
            entry['speed_bonus'],
            comp_str
        ])
        
    response = Response(si.getvalue(), mimetype='text/csv')
    now_ist = datetime.now(timezone.utc).astimezone(IST)
    response.headers['Content-Disposition'] = f"attachment; filename=leaderboard_{now_ist.strftime('%Y%m%d_%H%M%S')}.csv"
    return response
