-- Enable Row Level Security
ALTER TABLE tasks ENABLE ROW LEVEL SECURITY;

-- Policy: Select allowed for creator or assignee
CREATE POLICY "Users can view their own tasks or assigned tasks"
ON tasks FOR SELECT
USING (
  auth.uid() = creator_id
  OR
  auth.uid() = assignee_id
);

-- Policy: Insert allowed for creators only (assignee can be anyone)
CREATE POLICY "Users can create tasks"
ON tasks FOR INSERT
WITH CHECK (
  auth.uid() = creator_id
);

-- Policy: Update allowed for creator or assignee
-- Note: Logic for restricting 'is_completed' to assignee only is handled in application code (TaskController),
-- but we can enforce basic update rights here.
CREATE POLICY "Users can update their tasks or assigned tasks"
ON tasks FOR UPDATE
USING (
  auth.uid() = creator_id
  OR
  auth.uid() = assignee_id
);

-- Policy: Delete allowed for creator only
CREATE POLICY "Users can delete their own tasks"
ON tasks FOR DELETE
USING (
  auth.uid() = creator_id
);
