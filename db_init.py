from datetime import datetime, timedelta, timezone
from app import app, db
from models import User, Question, QuizConfig

def init_db():
    with app.app_context():
        print("Initializing database...")
        db.create_all()
        
        # 1. Create admin user if not exists
        admin_email = "admin@cheranalloorfaces.com"
        admin = User.query.filter_by(email=admin_email).first()
        if not admin:
            print(f"Creating default administrator: {admin_email}")
            admin = User(
                email=admin_email,
                username="admin",
                role="admin",
                is_verified=True
            )
            db.session.add(admin)
        else:
            print("Administrator account already exists.")

        # 2. Initialize default configurations
        configs = {
            'quiz_state': 'registration',  # registration_open (or 'registration')
            'quiz_start_time': (datetime.now() + timedelta(days=1)).isoformat(),
            'quiz_duration_mins': '15',
            'question_time_limit': '30'
        }
        
        for k, v in configs.items():
            cfg = QuizConfig.query.filter_by(key=k).first()
            if not cfg:
                print(f"Setting config '{k}' = '{v}'")
                cfg = QuizConfig(key=k, value=v)
                db.session.add(cfg)

        # 3. Seed Malayalam quiz questions
        print("Clearing old questions from SQLite database...")
        db.session.query(Question).delete()
        print("Seeding 10 Malayalam quiz questions...")
        questions = [
            Question(
                text="ചേരാനല്ലൂർ വിശുദ്ധ ഫ്രാൻസിസ് സേവിയേഴ്സ് ദേവാലയം സ്ഥാപിക്കപ്പെട്ട വർഷം?",
                option_a="1904",
                option_b="1804",
                option_c="1947",
                option_d="1504",
                correct_option="B",
                points=10
            ),
            Question(
                text="ചേരാനല്ലൂർ പള്ളിയോടനുബന്ധിച്ചുള്ള സന്യാസിനി സമൂഹം ഏത് കോൺഗ്രിഗേഷനിൽ പെടുന്നതാണ്?",
                option_a="CMC",
                option_b="SD",
                option_c="CMI",
                option_d="SH",
                correct_option="B",
                points=10
            ),
            Question(
                text="ചേരാനല്ലൂർ പള്ളി ഏത് ഫൊറോനയുടെ പരിധിയിൽ പെടുന്നു?",
                option_a="മലയാറ്റൂർ",
                option_b="പെരുമ്പാവൂർ",
                option_c="കാഞ്ഞൂർ",
                option_d="വല്ലം",
                correct_option="D",
                points=10
            ),
            Question(
                text="ചേരാനല്ലൂർ പ്രദേശം ഏത് താലൂക്കിൽ പെടുന്നു?",
                option_a="കൂവപ്പടി",
                option_b="കുറുപ്പംപടി",
                option_c="കാലടി",
                option_d="കുന്നത്തുനാട്",
                correct_option="D",
                points=10
            ),
            Question(
                text="പെരിയാർ നദി ഏത് കുന്നുകളിൽ നിന്ന് ആരംഭിക്കുന്നു?",
                option_a="മൂന്നാർ കുന്നുകൾ",
                option_b="മലയാറ്റൂർ മല",
                option_c="ശിവഗിരി കുന്നുകൾ",
                option_d="വാഗമൺ കുന്നുകൾ",
                correct_option="C",
                points=10
            ),
            Question(
                text="ചേരാനല്ലൂർ പള്ളി അങ്ങാടിയിൽ നിന്ന് തോട്ടുവ ഭാഗത്തേക്ക് പോകുമ്പോൾ കാണുന്ന ചിറയുടെ പേര്?",
                option_a="തൊട്ടുചിറ",
                option_b="മുട്ടുചിറ",
                option_c="മണപ്പാട്ടുചിറ",
                option_d="കൊടുവേലിചിറ",
                correct_option="B",
                points=10
            ),
            Question(
                text="ചേരാനല്ലൂർ അമ്പലപ്പറമ്പിനടുത്ത് കാണുന്ന ക്ഷേത്രത്തിലെ പ്രതിഷ്ഠ ഏത് ഭഗവാനാണ്?",
                option_a="വിഷ്ണു",
                option_b="ശിവൻ",
                option_c="ഭദ്രകാളി",
                option_d="കൃഷ്ണൻ",
                correct_option="B",
                points=10
            ),
            Question(
                text="ചേരാനല്ലൂർ ശ്രീ ശങ്കരനാരായണ ക്ഷേത്രം എവിടെ സ്ഥിതി ചെയ്യുന്നു?",
                option_a="തോട്ടുവാ",
                option_b="ഓച്ചാംതുരുത്ത്",
                option_c="ഇടവൂർ",
                option_d="വളവുംപടി",
                correct_option="C",
                points=10
            ),
            Question(
                text="ചേരാനല്ലൂർ പോസ്റ്റ് ഓഫീസ് പിൻകോഡ് എന്ത്?",
                option_a="653848",
                option_b="683544",
                option_c="664188",
                option_d="618535",
                correct_option="B",
                points=10
            ),
            Question(
                text="ചേരാനല്ലൂർ government higher secondary school എവിടെ സ്ഥിതി ചെയ്യുന്നു?",
                option_a="ചേരാനല്ലൂർ",
                option_b="ഐമുറി",
                option_c="കൂവപ്പടി",
                option_d="പെരുമ്പാവൂർ",
                correct_option="B",
                points=10
            )
        ]
        for q in questions:
            db.session.add(q)

        db.session.commit()
        print("Database initialization completed successfully!")

if __name__ == "__main__":
    init_db()
