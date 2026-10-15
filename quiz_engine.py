from datetime import datetime, timezone
from models import db, User, Question, UserQuizState, UserResponse, QuizConfig

def get_quiz_settings():
    """Retrieve quiz configurations with safe defaults."""
    state = QuizConfig.get_val('quiz_state', 'inactive')
    
    start_time_str = QuizConfig.get_val('quiz_start_time', '')
    start_time = None
    if start_time_str:
        try:
            start_time = datetime.fromisoformat(start_time_str)
        except ValueError:
            pass
            
    # Auto-live transition: if state is 'registration' and start_time has passed
    if state == 'registration' and start_time:
        now = datetime.now() if start_time.tzinfo is None else datetime.now(timezone.utc)
        if now >= start_time:
            QuizConfig.set_val('quiz_state', 'live')
            state = 'live'
            
    try:
        duration_mins = int(QuizConfig.get_val('quiz_duration_mins', '30'))
    except ValueError:
        duration_mins = 30
        
    try:
        time_limit_sec = int(QuizConfig.get_val('question_time_limit', '30'))
    except ValueError:
        time_limit_sec = 30
        
    return {
        'state': state,
        'start_time': start_time,
        'duration_mins': duration_mins,
        'time_limit_sec': time_limit_sec
    }

def get_or_create_quiz_state(user):
    """Get the user's quiz state or initialize it if the quiz is live."""
    settings = get_quiz_settings()
    if settings['state'] != 'live':
        return None
        
    state = UserQuizState.query.filter_by(user_id=user.id).first()
    if not state:
        # Check if user is already disqualified (should not happen if state doesn't exist, but safe)
        first_question = Question.query.order_by(Question.id.asc()).first()
        
        state = UserQuizState(
            user_id=user.id,
            current_question_id=first_question.id if first_question else None,
            current_question_started_at=datetime.now(timezone.utc) if first_question else None,
            quiz_started_at=datetime.now(timezone.utc),
            quiz_completed_at=None,
            is_disqualified=False,
            warnings_count=0
        )
        db.session.add(state)
        db.session.commit()
        
    return state

def submit_answer(user, question_id, selected_option):
    """
    Process an answer submission. Handles scoring, speed bonus, time-limits, 
    and advances user to the next question.
    """
    settings = get_quiz_settings()
    if settings['state'] != 'live':
        return False, "The quiz is not currently active."
        
    state = UserQuizState.query.filter_by(user_id=user.id).first()
    if not state:
        return False, "You have not started the quiz."
        
    if state.is_disqualified:
        return False, "You have been disqualified from this quiz."
        
    if state.quiz_completed_at:
        return False, "You have already completed the quiz."
        
    if state.current_question_id != question_id:
        # User submitted a different question (could be browser back or double submission)
        # We silently return success if they already answered it, or error
        existing_resp = UserResponse.query.filter_by(user_id=user.id, question_id=question_id).first()
        if existing_resp:
            return True, "Answer already recorded."
        return False, "Invalid question submission."

    question = Question.query.get(question_id)
    if not question:
        return False, "Question not found."

    # Verify time limit
    now = datetime.now(timezone.utc)
    started_at = state.current_question_started_at.replace(tzinfo=timezone.utc)
    elapsed_seconds = (now - started_at).total_seconds()
    
    # Give a 10-second grace period for latency to prevent slow devices/hosts from timing out
    time_limit = settings['time_limit_sec']
    is_timeout = elapsed_seconds > (time_limit + 10)

    is_correct = False
    points_earned = 0
    speed_bonus = 0
    
    # If selected_option is empty/None, it is a timeout
    if selected_option and not is_timeout:
        is_correct = (selected_option.upper() == question.correct_option.upper())
        if is_correct:
            points_earned = question.points
            # Speed bonus: 1 point for every second left under the limit
            time_spent = int(elapsed_seconds)
            speed_bonus = max(0, time_limit - time_spent)

    # Save user response
    response = UserResponse(
        user_id=user.id,
        question_id=question_id,
        selected_option=selected_option,
        is_correct=is_correct,
        points_earned=points_earned,
        speed_bonus=speed_bonus,
        response_time_ms=int(elapsed_seconds * 1000),
        submitted_at=now
    )
    db.session.add(response)

    # Find the next question
    next_question = Question.query.filter(Question.id > question_id).order_by(Question.id.asc()).first()
    
    if next_question:
        state.current_question_id = next_question.id
        state.current_question_started_at = now
    else:
        state.current_question_id = None
        state.current_question_started_at = None
        state.quiz_completed_at = now
        
    db.session.commit()
    return True, "Answer submitted successfully."

def handle_tab_switch_warning(user):
    """Increments tab switch warnings. Disqualifies user if warning count reaches 3."""
    state = UserQuizState.query.filter_by(user_id=user.id).first()
    if not state or state.quiz_completed_at or state.is_disqualified:
        return False, 0
        
    state.warnings_count += 1
    if state.warnings_count >= 3:
        state.is_disqualified = True
        state.disqualification_reason = "Exceeded tab switching limit (anti-cheating trigger)."
        db.session.commit()
        return True, state.warnings_count
        
    db.session.commit()
    return False, state.warnings_count

def reset_user_progress(user_id):
    """Admin tool to reset user's responses and quiz state."""
    UserResponse.query.filter_by(user_id=user_id).delete()
    state = UserQuizState.query.filter_by(user_id=user_id).first()
    if state:
        db.session.delete(state)
    db.session.commit()

def disqualify_user_manually(user_id, reason="Disqualified by administrator."):
    """Admin tool to manually disqualify a user."""
    state = UserQuizState.query.filter_by(user_id=user_id).first()
    if not state:
        state = UserQuizState(
            user_id=user_id,
            quiz_started_at=datetime.now(timezone.utc),
            is_disqualified=True,
            disqualification_reason=reason
        )
        db.session.add(state)
    else:
        state.is_disqualified = True
        state.disqualification_reason = reason
    db.session.commit()
