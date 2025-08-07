# Busylancer Backend API

A comprehensive backend API for the Busylancer freelancing platform built with PHP.

## Features

- **User Management**: Registration, authentication, and profile management for candidates and employers
- **Job Management**: Job posting, searching, filtering, and management
- **Application System**: Job applications with status tracking
- **Skills & Categories**: Skill management and categorization
- **Dashboard APIs**: Comprehensive dashboard data for both user types
- **Authentication**: JWT-based authentication with role-based access control
- **File Upload**: Resume, portfolio, and company logo uploads
- **Search & Filtering**: Advanced search capabilities across jobs and candidates

## Project Structure

```
backend/
├── api/                    # API endpoints
│   ├── auth.php           # Authentication (login, register, profile)
│   ├── jobs.php           # Job management
│   ├── applications.php   # Job applications
│   ├── candidates.php     # Candidate profiles and search
│   ├── employers.php      # Employer profiles
│   ├── dashboard.php      # Dashboard data
│   ├── skills.php         # Skills management
│   └── upload.php         # File uploads
├── config/                # Configuration files
│   ├── app.php           # Application configuration
│   └── database.php      # Database connection class
├── database/              # Database related files
│   └── schema.sql        # Database schema
├── middleware/            # Middleware functions
│   └── Auth.php          # Authentication middleware
├── models/               # Data models
│   ├── User.php          # User model
│   ├── CandidateProfile.php
│   ├── EmployerProfile.php
│   ├── Job.php
│   └── JobApplication.php
├── utils/                # Utility classes
│   ├── JWT.php           # JWT token handling
│   └── Response.php      # API response formatting
├── uploads/              # File upload directory
└── index.php            # Main API router
```

## Installation

1. **Database Setup**
   ```bash
   # Create MySQL database
   mysql -u root -p
   CREATE DATABASE busylancer_db;
   
   # Import schema
   mysql -u root -p busylancer_db < database/schema.sql
   ```

2. **Configuration**
   - Update database credentials in `config/app.php`
   - Set JWT secret key (make it long and secure)
   - Configure file upload paths
   - Set CORS origins for your frontend

3. **Web Server Setup**
   - Point your web server to the `backend` directory
   - Ensure PHP has write permissions to `uploads/` directory
   - Enable URL rewriting (Apache mod_rewrite or nginx equivalent)

## API Endpoints

### Authentication (`/api/auth`)
- `POST /api/auth/register` - User registration
- `POST /api/auth/login` - User login
- `GET /api/auth/profile` - Get user profile
- `PUT /api/auth/profile` - Update user profile
- `POST /api/auth/forgot-password` - Request password reset
- `POST /api/auth/reset-password` - Reset password
- `POST /api/auth/verify-email` - Verify email address

### Jobs (`/api/jobs`)
- `GET /api/jobs` - List all jobs
- `GET /api/jobs/search` - Search jobs with filters
- `GET /api/jobs/{id}` - Get specific job
- `POST /api/jobs/create` - Create new job (employer only)
- `PUT /api/jobs/{id}` - Update job (employer only)
- `DELETE /api/jobs/{id}` - Delete job (employer only)
- `GET /api/jobs/featured` - Get featured jobs
- `GET /api/jobs/recent` - Get recent jobs
- `GET /api/jobs/my-jobs` - Get employer's jobs
- `GET /api/jobs/stats` - Get job statistics

### Applications (`/api/applications`)
- `POST /api/applications/apply` - Apply to a job (candidate only)
- `GET /api/applications/my-applications` - Get candidate's applications
- `GET /api/applications/job/{job_id}` - Get job applications (employer only)
- `PUT /api/applications/{id}/status` - Update application status (employer only)
- `PUT /api/applications/{id}/withdraw` - Withdraw application (candidate only)
- `GET /api/applications/stats` - Get application statistics
- `GET /api/applications/recent` - Get recent applications

### Candidates (`/api/candidates`)
- `GET /api/candidates` - List all candidates
- `GET /api/candidates/search` - Search candidates with filters
- `GET /api/candidates/{id}` - Get candidate profile
- `GET /api/candidates/featured` - Get featured candidates
- `POST /api/candidates/{id}/skills` - Add skill to candidate
- `DELETE /api/candidates/{id}/skills` - Remove skill from candidate

### Dashboard (`/api/dashboard`)
- `GET /api/dashboard` - Get dashboard data (auto-detects user type)
- `GET /api/dashboard/candidate` - Get candidate dashboard
- `GET /api/dashboard/employer` - Get employer dashboard
- `GET /api/dashboard/stats` - Get platform statistics

### Skills (`/api/skills`)
- `GET /api/skills` - List all skills
- `GET /api/skills/categories` - Get skill categories
- `GET /api/skills/search` - Search skills

## Authentication

The API uses JWT (JSON Web Tokens) for authentication. Include the token in the Authorization header:

```
Authorization: Bearer <your_jwt_token>
```

### User Types
- **Candidate**: Can apply to jobs, manage their profile and applications
- **Employer**: Can post jobs, manage job postings, and review applications

## Request/Response Format

### Successful Response
```json
{
  "success": true,
  "message": "Success message",
  "data": {
    // Response data
  }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error message",
  "errors": {
    // Validation errors (optional)
  }
}
```

## Example Usage

### Register a New Candidate
```bash
curl -X POST http://localhost/backend/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "candidate@example.com",
    "password": "password123",
    "user_type": "candidate",
    "first_name": "John",
    "last_name": "Doe"
  }'
```

### Login
```bash
curl -X POST http://localhost/backend/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "candidate@example.com",
    "password": "password123"
  }'
```

### Search Jobs
```bash
curl -X GET "http://localhost/backend/api/jobs/search?keywords=javascript&job_type=hourly&location=remote" \
  -H "Authorization: Bearer <your_token>"
```

### Apply to a Job
```bash
curl -X POST http://localhost/backend/api/applications/apply \
  -H "Authorization: Bearer <your_token>" \
  -H "Content-Type: application/json" \
  -d '{
    "job_id": 1,
    "cover_letter": "I am interested in this position...",
    "proposed_rate": 50
  }'
```

## Database Schema

The database includes the following main tables:
- `users` - User accounts (both candidates and employers)
- `candidate_profiles` - Candidate-specific information
- `employer_profiles` - Employer-specific information
- `jobs` - Job postings
- `job_applications` - Job applications
- `skills` - Available skills
- `candidate_skills` - Candidate skill associations
- `job_skills` - Job skill requirements
- `messages` - Messaging system
- `notifications` - User notifications

## Security Features

- Password hashing using PHP's `password_hash()`
- JWT token-based authentication
- Role-based access control
- Input validation and sanitization
- SQL injection prevention through prepared statements
- CORS configuration for frontend integration

## Configuration Options

Key configuration options in `config/app.php`:
- Database connection settings
- JWT secret and expiration
- File upload limits and allowed types
- Email configuration for notifications
- CORS origins
- Pagination limits

## Error Handling

The API provides comprehensive error handling with appropriate HTTP status codes:
- `200` - Success
- `201` - Created
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `409` - Conflict
- `422` - Validation Error
- `500` - Internal Server Error

## Development

- Set `APP_ENV` to `development` for detailed error messages
- Use the `/api` endpoint to see all available API routes
- Check the database schema in `database/schema.sql` for data structure

## Contributing

1. Follow PSR-4 autoloading standards
2. Use prepared statements for all database queries
3. Implement proper error handling
4. Add input validation for all endpoints
5. Document any new API endpoints

## License

This project is part of the Busylancer platform.