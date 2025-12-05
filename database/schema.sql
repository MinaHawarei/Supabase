-- Create extension for UUID generation
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- Create tasks table
CREATE TABLE public.tasks (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  creator_id uuid NOT NULL,
  assignee_id uuid NOT NULL,
  title text NOT NULL,
  description text,
  due_date timestamptz NOT NULL,
  priority text NOT NULL CHECK (priority IN ('low','medium','high')) DEFAULT 'medium',
  is_completed boolean DEFAULT false,
  attachment_key text,
  attachment_mime text,
  created_at timestamptz DEFAULT now(),
  updated_at timestamptz DEFAULT now()
);

-- Create indexes
CREATE INDEX idx_tasks_assignee ON public.tasks (assignee_id);
CREATE INDEX idx_tasks_due_date ON public.tasks (due_date);

